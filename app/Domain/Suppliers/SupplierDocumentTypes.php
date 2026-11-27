<?php

namespace App\Domain\Suppliers;

/**
 * Eligibility document types. The required set is a starting configuration: confirm it against the
 * current RA 12009 IRR and GPPB issuances (e.g. PhilGEPS registration level, which documents a
 * Platinum registration replaces, mode-specific requirements) before production use.
 */
final class SupplierDocumentTypes
{
    /** @var array<string, array{label: string, expires: bool}> */
    public const TYPES = [
        'philgeps_registration' => ['label' => 'PhilGEPS registration certificate', 'expires' => true],
        'business_permit' => ['label' => "Mayor's / business permit", 'expires' => true],
        'tax_clearance' => ['label' => 'Tax clearance', 'expires' => true],
        'sec_dti_cda_registration' => ['label' => 'SEC / DTI / CDA registration', 'expires' => false],
        'audited_financial_statements' => ['label' => 'Audited financial statements', 'expires' => false],
        'omnibus_sworn_statement' => ['label' => 'Omnibus sworn statement', 'expires' => false],
        'professional_license' => ['label' => 'Professional license / PCAB license', 'expires' => true],
        'other' => ['label' => 'Other', 'expires' => false],
    ];

    /** Documents that must be on file, verified and unexpired for a supplier to be eligible. */
    public const REQUIRED = ['philgeps_registration', 'business_permit', 'tax_clearance'];

    /** Days before expiry when an "expiring document" notice is raised. */
    public const EXPIRY_NOTICE_DAYS = 30;

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_map(fn (array $t) => $t['label'], self::TYPES);
    }

    public static function label(string $type): string
    {
        return self::TYPES[$type]['label'] ?? $type;
    }
}
