<?php

namespace App\Services;

use App\Contracts\DomainDnsLookup;

class PhpDomainDnsLookup implements DomainDnsLookup
{
    /**
     * @return list<string>
     */
    public function txtRecords(string $domain): array
    {
        $records = @dns_get_record($domain, DNS_TXT);

        if ($records === false) {
            return [];
        }

        $values = [];

        foreach ($records as $record) {
            $text = $record['txt'] ?? null;

            if (is_string($text) && $text !== '') {
                $values[] = $text;
            }
        }

        return $values;
    }
}
