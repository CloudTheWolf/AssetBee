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
    */

    'soft_delete_retention_days' => (int) env('ASSETS_SOFT_DELETE_RETENTION_DAYS', 30),

];
