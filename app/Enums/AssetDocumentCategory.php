<?php

namespace App\Enums;

enum AssetDocumentCategory: string
{
    case Invoice = 'invoice';
    case Receipt = 'receipt';
    case Contract = 'contract';
    case Warranty = 'warranty';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Invoice => __('Invoice'),
            self::Receipt => __('Receipt'),
            self::Contract => __('Contract'),
            self::Warranty => __('Warranty'),
            self::Other => __('Other'),
        };
    }
}
