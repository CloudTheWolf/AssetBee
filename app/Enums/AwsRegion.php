<?php

namespace App\Enums;

enum AwsRegion: string
{
    case UsEast1 = 'us-east-1';
    case UsEast2 = 'us-east-2';
    case UsWest1 = 'us-west-1';
    case UsWest2 = 'us-west-2';
    case AfSouth1 = 'af-south-1';
    case ApEast1 = 'ap-east-1';
    case ApSouth1 = 'ap-south-1';
    case ApSouth2 = 'ap-south-2';
    case ApNortheast1 = 'ap-northeast-1';
    case ApNortheast2 = 'ap-northeast-2';
    case ApNortheast3 = 'ap-northeast-3';
    case ApSoutheast1 = 'ap-southeast-1';
    case ApSoutheast2 = 'ap-southeast-2';
    case ApSoutheast3 = 'ap-southeast-3';
    case ApSoutheast4 = 'ap-southeast-4';
    case CaCentral1 = 'ca-central-1';
    case CaWest1 = 'ca-west-1';
    case EuCentral1 = 'eu-central-1';
    case EuCentral2 = 'eu-central-2';
    case EuWest1 = 'eu-west-1';
    case EuWest2 = 'eu-west-2';
    case EuWest3 = 'eu-west-3';
    case EuNorth1 = 'eu-north-1';
    case EuSouth1 = 'eu-south-1';
    case EuSouth2 = 'eu-south-2';
    case IlCentral1 = 'il-central-1';
    case MeCentral1 = 'me-central-1';
    case MeSouth1 = 'me-south-1';
    case SaEast1 = 'sa-east-1';

    public function label(): string
    {
        return match ($this) {
            self::UsEast1 => 'US East (N. Virginia)',
            self::UsEast2 => 'US East (Ohio)',
            self::UsWest1 => 'US West (N. California)',
            self::UsWest2 => 'US West (Oregon)',
            self::AfSouth1 => 'Africa (Cape Town)',
            self::ApEast1 => 'Asia Pacific (Hong Kong)',
            self::ApSouth1 => 'Asia Pacific (Mumbai)',
            self::ApSouth2 => 'Asia Pacific (Hyderabad)',
            self::ApNortheast1 => 'Asia Pacific (Tokyo)',
            self::ApNortheast2 => 'Asia Pacific (Seoul)',
            self::ApNortheast3 => 'Asia Pacific (Osaka)',
            self::ApSoutheast1 => 'Asia Pacific (Singapore)',
            self::ApSoutheast2 => 'Asia Pacific (Sydney)',
            self::ApSoutheast3 => 'Asia Pacific (Jakarta)',
            self::ApSoutheast4 => 'Asia Pacific (Melbourne)',
            self::CaCentral1 => 'Canada (Central)',
            self::CaWest1 => 'Canada West (Calgary)',
            self::EuCentral1 => 'Europe (Frankfurt)',
            self::EuCentral2 => 'Europe (Zurich)',
            self::EuWest1 => 'Europe (Ireland)',
            self::EuWest2 => 'Europe (London)',
            self::EuWest3 => 'Europe (Paris)',
            self::EuNorth1 => 'Europe (Stockholm)',
            self::EuSouth1 => 'Europe (Milan)',
            self::EuSouth2 => 'Europe (Spain)',
            self::IlCentral1 => 'Israel (Tel Aviv)',
            self::MeCentral1 => 'Middle East (UAE)',
            self::MeSouth1 => 'Middle East (Bahrain)',
            self::SaEast1 => 'South America (São Paulo)',
        };
    }
}
