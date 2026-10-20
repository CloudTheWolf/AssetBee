<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Soft-delete retention
    |--------------------------------------------------------------------------
    |
    | Soft-deleted assets keep their row for this many days so accidental
    | deletes can be restored. Allocations (seats, assignees, hosts) are
    | released as soon as the purge job runs. After retention expires the
    | row is permanently removed.
    |
    | Hardware uses a short window so a device can be re-added by the next
    | Drone inventory sync shortly after delete.
    |
    */

    'soft_delete_retention_days' => (int) env('ASSETS_SOFT_DELETE_RETENTION_DAYS', 30),

    'hardware_soft_delete_retention_minutes' => (int) env('ASSETS_HARDWARE_SOFT_DELETE_RETENTION_MINUTES', 1),

];
