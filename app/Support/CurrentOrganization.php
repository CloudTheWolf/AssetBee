<?php

namespace App\Support;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class CurrentOrganization
{
    public const SESSION_KEY = 'current_organization_id';

    public static function id(): ?int
    {
        $id = Session::get(self::SESSION_KEY);

        return is_numeric($id) ? (int) $id : null;
    }

    public static function get(): ?Organization
    {
        $id = self::id();

        if ($id === null) {
            return null;
        }

        /** @var User|null $user */
        $user = Auth::user();

        if ($user === null) {
            return null;
        }

        return $user->organizations()->where('organizations.id', $id)->first();
    }

    public static function require(): Organization
    {
        $organization = self::get();

        if ($organization === null) {
            abort(403, __('No organization selected.'));
        }

        return $organization;
    }

    public static function set(Organization $organization): void
    {
        Session::put(self::SESSION_KEY, $organization->id);
    }

    public static function clear(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    public static function roleFor(User $user, ?Organization $organization = null): ?OrganizationRole
    {
        $organization ??= self::get();

        if ($organization === null) {
            return null;
        }

        $membership = $user->organizations()
            ->where('organizations.id', $organization->id)
            ->first();

        if ($membership === null) {
            return null;
        }

        $role = $membership->pivot->role;

        return $role instanceof OrganizationRole
            ? $role
            : OrganizationRole::from((string) $role);
    }

    public static function ensureSelected(User $user): ?Organization
    {
        $current = self::get();

        if ($current !== null) {
            return $current;
        }

        $organization = $user->organizations()->orderBy('organizations.name')->first();

        if ($organization !== null) {
            self::set($organization);
        }

        return $organization;
    }
}
