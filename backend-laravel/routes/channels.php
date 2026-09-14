<?php

use Illuminate\Support\Facades\Broadcast;

/*
| Private channel per tenant+branch for live order updates — subscribed to
| by the Kitchen Display (admin-web), the customer order-tracking page
| (public-web), and the waiter app (mobile-app). Authorization confirms the
| authenticated user actually belongs to that tenant/branch before letting
| them listen.
*/
Broadcast::channel('tenant.{tenantId}.branch.{branchId}.orders', function ($user, $tenantId, $branchId) {
    return $user->tenant_id === $tenantId
        && (is_null($user->branch_id) || $user->branch_id === $branchId);
});
