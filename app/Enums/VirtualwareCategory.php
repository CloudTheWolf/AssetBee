<?php

namespace App\Enums;

enum VirtualwareCategory: string
{
    case Vm = 'vm';
    case Container = 'container';
    case Database = 'database';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Vm => __('VM'),
            self::Container => __('Container'),
            self::Database => __('Database'),
            self::Other => __('Other'),
        };
    }
}
