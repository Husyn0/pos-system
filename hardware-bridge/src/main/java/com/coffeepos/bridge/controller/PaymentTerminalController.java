package com.coffeepos.bridge.controller;

import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RestController;

import java.util.Map;

/**
 * Generic seam for LAN-connected card terminals (PAX, Ingenico, Verifone
 * ECR protocols) that accept a "start a $X.XX sale" command over
 * TCP/serial and report back approved/declined + a masked card summary.
 *
 * If you're using Stripe Terminal instead, the SDK talks to the reader
 * directly from the desktop/mobile app and this controller isn't needed
 * for that flow — it exists for the class of terminal that expects a
 * local integrator (this bridge) to drive it.
 *
 * Swap swap the TODO body for your provider's SDK/protocol client.
 */
@RestController
@RequestMapping("/payment-terminal")
public class PaymentTerminalController {

    @PostMapping("/charge")
    public ResponseEntity<?> charge(@RequestBody Map<String, Object> request) {
        // TODO: replace with real ECR protocol call, e.g.:
        // PaxTerminalClient.doSale(amountCents, referenceId);
        double amount = ((Number) request.getOrDefault("amount", 0)).doubleValue();
        return ResponseEntity.ok(Map.of(
                "status", "approved",
                "amount", amount,
                "card_brand", "visa",
                "card_last4", "4242",
                "terminal_reference", "stub-" + System.currentTimeMillis()
        ));
    }
}
