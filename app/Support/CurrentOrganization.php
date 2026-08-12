<?php

namespace App\Support;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
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

        if ($user->hasSystemAccess()) {
            return Organization::query()->find($id);
        }

        if (! $user->isCustomer()) {
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

    /**
     * @throws AuthorizationException
     */
    public static function set(Organization $organization, ?User $user = null): void
    {
        $user ??= Auth::user();

        if (! $user instanceof User || ! self::canSelect($user, $organization)) {
            throw new AuthorizationException(__('You cannot select this organization.'));
        }

        Session::put(self::SESSION_KEY, $organization->id);
    }

    public static function canSelect(User $user, Organization $organization): bool
    {
        if ($user->hasSystemAccess()) {
            return true;
        }

        return $user->isCustomer()
            && $user->organizations()->where('organizations.id', $organization->id)->exists();
    }

    public static function isManagedBySystem(User $user, Organization $organization): bool
    {
        return $user->hasSystemAccess() && self::id() === $organization->id;
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

        $pivot = $membership->getRelation('pivot');

        if (! $pivot instanceof OrganizationUser) {
            return null;
        }

        return $pivot->role;
    }

    public static function ensureSelected(User $user): ?Organization
    {
        $current = self::get();

        if ($current !== null) {
            return $current;
        }

        if (! $user->isCustomer()) {
            self::clear();

            return null;
        }

        $organization = $user->organizations()->orderBy('organizations.name')->first();

        if ($organization !== null) {
            self::set($organization, $user);
        }

        return $organization;
    }
}
