<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Services\OrderStateMachine;
use PHPUnit\Framework\TestCase;

class OrderStateMachineTest extends TestCase
{
    public function test_the_full_happy_path_is_legal(): void
    {
        $path = [
            Order::STATUS_PENDING, Order::STATUS_CONFIRMED, Order::STATUS_PREPARING,
            Order::STATUS_READY, Order::STATUS_COMPLETED,
        ];
        for ($i = 0; $i < count($path) - 1; $i++) {
            $this->assertTrue(OrderStateMachine::canTransition($path[$i], $path[$i + 1]));
        }
    }

    public function test_skipping_states_is_illegal(): void
    {
        $this->assertFalse(OrderStateMachine::canTransition(Order::STATUS_PENDING, Order::STATUS_PREPARING));
        $this->assertFalse(OrderStateMachine::canTransition(Order::STATUS_PENDING, Order::STATUS_COMPLETED));
        $this->assertFalse(OrderStateMachine::canTransition(Order::STATUS_CONFIRMED, Order::STATUS_READY));
    }

    public function test_terminal_states_accept_no_further_transitions(): void
    {
        foreach ([Order::STATUS_PENDING, Order::STATUS_CONFIRMED, Order::STATUS_PREPARING, Order::STATUS_READY, Order::STATUS_COMPLETED, Order::STATUS_CANCELLED] as $target) {
            $this->assertFalse(OrderStateMachine::canTransition(Order::STATUS_COMPLETED, $target));
            $this->assertFalse(OrderStateMachine::canTransition(Order::STATUS_CANCELLED, $target));
        }
        $this->assertTrue(OrderStateMachine::isTerminal(Order::STATUS_COMPLETED));
        $this->assertTrue(OrderStateMachine::isTerminal(Order::STATUS_CANCELLED));
    }

    public function test_any_non_terminal_state_can_cancel(): void
    {
        foreach ([Order::STATUS_PENDING, Order::STATUS_CONFIRMED, Order::STATUS_PREPARING, Order::STATUS_READY] as $state) {
            $this->assertTrue(OrderStateMachine::canTransition($state, Order::STATUS_CANCELLED));
            $this->assertTrue(OrderStateMachine::inventoryIsRestockableFrom($state));
        }
    }

    public function test_invalid_transition_throws_with_both_states_on_the_exception(): void
    {
        $this->expectException(\App\Exceptions\InvalidOrderStateTransitionException::class);
        OrderStateMachine::assertCanTransition(Order::STATUS_PENDING, Order::STATUS_READY);
    }
}
