<?php

namespace App\Services;

use App\Exceptions\InvalidOrderStateTransitionException;
use App\Models\Order;

/**
 * The single source of truth for which order status transitions are legal.
 * Every write path (kitchen display, POS, mobile waiter app, public
 * tracking) goes through this — there is no other way to change an
 * order's status in the codebase, which is what makes "an order can't
 * silently jump from pending to completed" an enforced invariant rather
 * than a convention people can forget.
 *
 *   pending --> confirmed --> preparing --> ready --> completed
 *      \            \             \           \
 *       -------------+-------------+-----------+---> cancelled
 *
 * completed and cancelled are terminal. Getting money back out of a
 * completed order is a *refund* (PaymentService), not a status change —
 * that distinction matters because a completed order already consumed
 * inventory and (usually) fed a real customer; "un-completing" it would
 * misrepresent what actually happened.
 */
class OrderStateMachine
{
    private const TRANSITIONS = [
        Order::STATUS_PENDING => [Order::STATUS_CONFIRMED, Order::STATUS_CANCELLED],
        Order::STATUS_CONFIRMED => [Order::STATUS_PREPARING, Order::STATUS_CANCELLED],
        Order::STATUS_PREPARING => [Order::STATUS_READY, Order::STATUS_CANCELLED],
        Order::STATUS_READY => [Order::STATUS_COMPLETED, Order::STATUS_CANCELLED],
        Order::STATUS_COMPLETED => [],
        Order::STATUS_CANCELLED => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public static function assertCanTransition(string $from, string $to): void
    {
        if (! self::canTransition($from, $to)) {
            throw new InvalidOrderStateTransitionException($from, $to);
        }
    }

    public static function isTerminal(string $status): bool
    {
        return empty(self::TRANSITIONS[$status] ?? null);
    }

    /** Whether inventory that was committed at order creation is still
     *  "reserved but unconsumed" and therefore eligible to be restocked
     *  if the order is cancelled from this state. */
    public static function inventoryIsRestockableFrom(string $status): bool
    {
        return in_array($status, [
            Order::STATUS_PENDING, Order::STATUS_CONFIRMED, Order::STATUS_PREPARING, Order::STATUS_READY,
        ], true);
    }
}
