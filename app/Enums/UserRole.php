<?php

namespace App\Enums;

enum UserRole: string
{
    case PtoAdministrator = 'pto_administrator';
    case Lgu = 'lgu';
    case Establishment = 'establishment';

    /**
     * The job title shown under the user's name in dashboard headers.
     */
    public function title(): string
    {
        return match ($this) {
            self::PtoAdministrator => 'PTO Administrator',
            self::Lgu => 'LGU Tourism Personnel',
            self::Establishment => 'Tourism Establishment',
        };
    }

    /**
     * Short badge shown in the dashboard sidebar (mirrors the design system's
     * role chip: PTO / LGU / EST).
     */
    public function badge(): string
    {
        return match ($this) {
            self::PtoAdministrator => 'PTO',
            self::Lgu => 'LGU',
            self::Establishment => 'EST',
        };
    }

    /**
     * The label used on the login screen's role picker.
     */
    public function loginLabel(): string
    {
        return match ($this) {
            self::PtoAdministrator => 'PTO Administrator',
            self::Lgu => 'LGU / Municipal Tourism',
            self::Establishment => 'Tourism Establishment',
        };
    }

    /**
     * The description shown under the role on the login screen's role picker.
     */
    public function loginDescription(): string
    {
        return match ($this) {
            self::PtoAdministrator => 'Provincial-level monitoring, consolidation and analytics',
            self::Lgu => 'Review establishment reports for your municipality',
            self::Establishment => 'Record arrivals, submit reports, manage your QR code',
        };
    }

    /**
     * Named route for this role's dashboard.
     */
    public function dashboardRouteName(): string
    {
        return match ($this) {
            self::PtoAdministrator => 'pto.dashboard',
            self::Lgu => 'lgu.dashboard',
            self::Establishment => 'establishment.dashboard',
        };
    }
}
