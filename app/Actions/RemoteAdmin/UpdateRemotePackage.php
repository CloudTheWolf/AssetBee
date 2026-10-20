<?php

namespace App\Actions\RemoteAdmin;

use App\Models\RemotePackage;
use Illuminate\Validation\ValidationException;

class UpdateRemotePackage
{
    public function __construct(private CreateRemotePackage $createRemotePackage) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(RemotePackage $package, array $input): RemotePackage
    {
        $package->update($this->createRemotePackage->validate($input));

        return $package->refresh();
    }
}
