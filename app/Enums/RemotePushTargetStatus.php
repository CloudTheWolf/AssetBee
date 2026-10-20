<?php

namespace App\Enums;

enum RemotePushTargetStatus: string
{
    case Pending = 'pending';
    case Delivered = 'delivered';
    case AwaitingUser = 'awaiting_user';
    case Postponed = 'postponed';
    case Scheduled = 'scheduled';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Expired = 'expired';
    case Unsupported = 'unsupported';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Delivered => __('Delivered'),
            self::AwaitingUser => __('Awaiting user'),
            self::Postponed => __('Postponed'),
            self::Scheduled => __('Scheduled'),
            self::Running => __('Running'),
            self::Succeeded => __('Succeeded'),
            self::Failed => __('Failed'),
            self::Expired => __('Expired'),
            self::Unsupported => __('Unsupported'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending, self::Delivered, self::AwaitingUser, self::Postponed, self::Scheduled, self::Running => 'amber',
            self::Succeeded => 'green',
            self::Failed, self::Expired => 'red',
            self::Unsupported => 'zinc',
        };
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Succeeded, self::Failed, self::Expired, self::Unsupported => true,
            default => false,
        };
    }

    public function isIncomplete(): bool
    {
        return ! $this->isTerminal();
    }

    /**
     * @return list<self>
     */
    public static function incompleteCases(): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $status): bool => $status->isIncomplete(),
        ));
    }
}
