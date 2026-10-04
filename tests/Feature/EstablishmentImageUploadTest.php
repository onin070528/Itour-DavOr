<?php

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\EstablishmentImage;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

function establishmentImageCategoryFixture(): Category
{
    return Category::query()->firstOrCreate(
        ['cat_name' => 'Accommodation'],
        ['cat_sort_order' => 1, 'cat_is_active' => true, 'cat_is_qr_enabled' => true]
    );
}

function establishmentImageListingFixture(array $overrides = []): Listing
{
    $category = establishmentImageCategoryFixture();

    return Listing::query()->create(array_merge([
        'slug' => Str::slug('image-upload-fixture-'.Str::random(6)),
        'name' => 'Upload Test Resort',
        'category' => 'accommodation',
        'cat_id' => $category->cat_id,
        'municipality' => 'City of Mati',
        'barangay' => 'Dahican',
        // DRAFT, not PUBLISHED: an establishment's own photo management is
        // only allowed while its package is editable (see
        // App\Policies\ImagePolicy, the establishment self-review merge).
        // Tests exercising LGU/PTO-side or public-route behavior override
        // this explicitly where PUBLISHED actually matters.
        'status' => 'DRAFT',
    ], $overrides));
}

function establishmentImageUserFixture(Listing $listing): User
{
    return User::factory()->create(['role' => UserRole::Establishment, 'establishment_id' => $listing->id]);
}

/**
 * A minimal, real, decodable JPEG with a crafted EXIF APP1 segment carrying
 * GPS coordinates (GPSLatitudeRef/GPSLatitude) — built by hand, byte by
 * byte, rather than shipped as a binary fixture, so the test is self
 * contained. php-exif isn't installed in this environment, so the
 * assertion checks for the literal "Exif" marker bytes rather than using
 * exif_read_data().
 */
function jpegWithGpsExifBytes(): string
{
    $tiffHeader = "II\x2A\x00".pack('V', 8);

    $gpsIfdOffsetPlaceholder = 26;
    $ifd0 = pack('v', 1)
        .pack('v', 0x8825).pack('v', 4).pack('V', 1).pack('V', $gpsIfdOffsetPlaceholder)
        .pack('V', 0);

    $gpsExternalOffset = 56;
    $gpsIfd = pack('v', 2)
        .pack('v', 0x0001).pack('v', 2).pack('V', 2)."N\x00\x00\x00"
        .pack('v', 0x0002).pack('v', 5).pack('V', 3).pack('V', $gpsExternalOffset)
        .pack('V', 0);

    $gpsRationals = pack('V', 7).pack('V', 1).pack('V', 30).pack('V', 1).pack('V', 0).pack('V', 1);

    $tiffBlob = $tiffHeader.$ifd0.$gpsIfd.$gpsRationals;
    $exifBlob = "Exif\x00\x00".$tiffBlob;
    $app1 = "\xFF\xE1".pack('n', strlen($exifBlob) + 2).$exifBlob;

    $objGdImage = imagecreatetruecolor(1600, 1200);
    imagefill($objGdImage, 0, 0, imagecolorallocate($objGdImage, 80, 140, 180));
    ob_start();
    imagejpeg($objGdImage, null, 90);
    $strPlainJpeg = ob_get_clean();
    imagedestroy($objGdImage);

    // Splice the APP1/EXIF segment in right after the SOI marker (FF D8).
    return substr($strPlainJpeg, 0, 2).$app1.substr($strPlainJpeg, 2);
}

function uploadedFixtureFile(string $strBinaryContent, string $strName, string $strMimeType): UploadedFile
{
    $strTempPath = tempnam(sys_get_temp_dir(), 'itourimg');
    file_put_contents($strTempPath, $strBinaryContent);

    return new UploadedFile($strTempPath, $strName, $strMimeType, null, true);
}

test('a photo is accepted, processed, and stored, and submits the establishment for LGU approval', function () {
    $listing = establishmentImageListingFixture();
    $user = establishmentImageUserFixture($listing);

    $response = test()->actingAs($user)->post(route('establishment.images.store'), [
        'listing_id' => $listing->id,
        'photos' => [UploadedFile::fake()->image('beach.jpg', 1600, 1200)],
        'ownership_declared' => '1',
        'credit' => 'Photo by Test',
    ]);

    $response->assertSessionHasNoErrors();
    $image = EstablishmentImage::query()->where('listing_id', $listing->id)->first();
    expect($image)->not->toBeNull();
    expect($image->img_status->value)->toBe('PENDING');
    expect($image->img_source_role->value)->toBe('ESTABLISHMENT');
    expect(Storage::disk('local')->exists($image->img_path))->toBeTrue();
    expect(Storage::disk('local')->exists($image->img_thumbnail_path))->toBeTrue();
    // Never the uploaded filename.
    expect($image->img_path)->not->toContain('beach');
});

test('a script renamed to .jpg is rejected', function () {
    $listing = establishmentImageListingFixture();
    $user = establishmentImageUserFixture($listing);
    $objFakeScript = uploadedFixtureFile('<?php echo "not a photo"; ?>', 'malicious.jpg', 'image/jpeg');

    $response = test()->actingAs($user)->post(route('establishment.images.store'), [
        'listing_id' => $listing->id,
        'photos' => [$objFakeScript],
        'ownership_declared' => '1',
    ]);

    $response->assertSessionHasErrors();
    expect(EstablishmentImage::query()->where('listing_id', $listing->id)->count())->toBe(0);
});

test('a 6 MB file is rejected', function () {
    $listing = establishmentImageListingFixture();
    $user = establishmentImageUserFixture($listing);

    $response = test()->actingAs($user)->post(route('establishment.images.store'), [
        'listing_id' => $listing->id,
        'photos' => [UploadedFile::fake()->image('big.jpg', 2000, 1500)->size(6144)],
        'ownership_declared' => '1',
    ]);

    $response->assertSessionHasErrors();
    expect(EstablishmentImage::query()->where('listing_id', $listing->id)->count())->toBe(0);
});

test('a photo smaller than the minimum dimensions is rejected', function () {
    $listing = establishmentImageListingFixture();
    $user = establishmentImageUserFixture($listing);

    $response = test()->actingAs($user)->post(route('establishment.images.store'), [
        'listing_id' => $listing->id,
        'photos' => [UploadedFile::fake()->image('small.jpg', 400, 300)],
        'ownership_declared' => '1',
    ]);

    $response->assertSessionHasErrors();
});

test('uploading without the ownership checkbox is rejected', function () {
    $listing = establishmentImageListingFixture();
    $user = establishmentImageUserFixture($listing);

    $response = test()->actingAs($user)->post(route('establishment.images.store'), [
        'listing_id' => $listing->id,
        'photos' => [UploadedFile::fake()->image('beach.jpg', 1600, 1200)],
    ]);

    $response->assertSessionHasErrors('ownership_declared');
});

test('a duplicate photo (by processed hash) is rejected within the same establishment', function () {
    $listing = establishmentImageListingFixture();
    $user = establishmentImageUserFixture($listing);
    $photo = UploadedFile::fake()->image('beach.jpg', 1600, 1200);

    test()->actingAs($user)->post(route('establishment.images.store'), [
        'listing_id' => $listing->id,
        'photos' => [$photo],
        'ownership_declared' => '1',
    ])->assertSessionHasNoErrors();

    $samePhotoAgain = UploadedFile::fake()->image('beach-again.jpg', 1600, 1200);
    $response = test()->actingAs($user)->post(route('establishment.images.store'), [
        'listing_id' => $listing->id,
        'photos' => [$samePhotoAgain],
        'ownership_declared' => '1',
    ]);

    $response->assertSessionHasErrors();
    expect(EstablishmentImage::query()->where('listing_id', $listing->id)->count())->toBe(1);
});

test('uploading beyond the maximum live image count is rejected', function () {
    $listing = establishmentImageListingFixture();
    $user = establishmentImageUserFixture($listing);

    foreach (range(1, config('establishment_images.max_live_images_per_listing')) as $i) {
        // Each photo needs a distinct processed hash — same-size fake
        // images from UploadedFile::fake() render identical pixels, which
        // would otherwise trip the duplicate-hash rule instead of this cap.
        test()->actingAs($user)->post(route('establishment.images.store'), [
            'listing_id' => $listing->id,
            'photos' => [UploadedFile::fake()->image("photo-{$i}.jpg", 1600 + $i, 1200)],
            'ownership_declared' => '1',
        ])->assertSessionHasNoErrors();
    }

    $response = test()->actingAs($user)->post(route('establishment.images.store'), [
        'listing_id' => $listing->id,
        'photos' => [UploadedFile::fake()->image('one-too-many.jpg', 1600, 1200)],
        'ownership_declared' => '1',
    ]);

    $response->assertSessionHasErrors();
});

test('an establishment cannot upload for another establishment', function () {
    $listing = establishmentImageListingFixture();
    $otherListing = establishmentImageListingFixture(['name' => 'Someone Else Resort']);
    $user = establishmentImageUserFixture($listing);

    $response = test()->actingAs($user)->post(route('establishment.images.store'), [
        'listing_id' => $otherListing->id,
        'photos' => [UploadedFile::fake()->image('beach.jpg', 1600, 1200)],
        'ownership_declared' => '1',
    ]);

    $response->assertForbidden();
});

test('a PTO upload publishes immediately with no approval step', function () {
    $listing = establishmentImageListingFixture();
    $pto = User::factory()->create(['role' => UserRole::PtoAdministrator]);

    $response = test()->actingAs($pto)->post(route('pto.images.store'), [
        'listing_id' => $listing->id,
        'photos' => [UploadedFile::fake()->image('beach.jpg', 1600, 1200)],
        'ownership_declared' => '1',
    ]);

    $response->assertSessionHasNoErrors();
    $image = EstablishmentImage::query()->where('listing_id', $listing->id)->first();
    expect($image->img_status->value)->toBe('PUBLISHED');
    expect($image->img_source_role->value)->toBe('PTO');
});

test('an uploaded photo stored copy has no EXIF/GPS data, even when the original did', function () {
    $listing = establishmentImageListingFixture();
    $user = establishmentImageUserFixture($listing);
    $objFixtureBytes = jpegWithGpsExifBytes();
    expect($objFixtureBytes)->toContain('Exif');

    $objUploadedFile = uploadedFixtureFile($objFixtureBytes, 'gps-photo.jpg', 'image/jpeg');

    $response = test()->actingAs($user)->post(route('establishment.images.store'), [
        'listing_id' => $listing->id,
        'photos' => [$objUploadedFile],
        'ownership_declared' => '1',
    ]);

    $response->assertSessionHasNoErrors();
    $image = EstablishmentImage::query()->where('listing_id', $listing->id)->first();
    $strStoredContents = Storage::disk('local')->get($image->img_path);

    expect($strStoredContents)->not->toContain('Exif');
});
