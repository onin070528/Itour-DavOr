/**
 * LGU Add Establishment form — Photos section preview
 * (resources/views/lgu/directory/establishments/partials/photo-upload.blade.php).
 * Shows a thumbnail and file name for every file chosen in
 * `[data-photo-input]`. Preview only: nothing is uploaded until the form is
 * saved, and the server re-checks every file (real type, size, dimensions).
 * "Remove" before saving is done by choosing the files again.
 */
export function initEstablishmentPhotoPreviews() {
    document.querySelectorAll('[data-photo-upload-preview]').forEach((section) => {
        const input = section.querySelector('[data-photo-input]');
        const list = section.querySelector('[data-photo-preview-list]');
        if (!input || !list) return;

        let objectUrls = [];

        input.addEventListener('change', () => {
            objectUrls.forEach((url) => URL.revokeObjectURL(url));
            objectUrls = [];
            list.replaceChildren();

            Array.from(input.files ?? []).forEach((file) => {
                const url = URL.createObjectURL(file);
                objectUrls.push(url);

                const item = document.createElement('li');
                item.className = 'overflow-hidden rounded-sm border border-sand-200 bg-sand-50';

                const image = document.createElement('img');
                image.src = url;
                image.alt = '';
                image.className = 'h-24 w-full object-cover';

                const caption = document.createElement('p');
                caption.className = 'truncate px-2 py-1 text-xs text-sand-600';
                caption.textContent = file.name;

                item.append(image, caption);
                list.append(item);
            });
        });
    });
}
