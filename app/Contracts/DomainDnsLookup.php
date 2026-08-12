<?php

namespace App\Contracts;

interface DomainDnsLookup
{
    /**
     * @return list<string>
     */
    public function txtRecords(string $domain): array;
}
