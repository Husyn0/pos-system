package com.coffeepos.bridge.controller;

import com.coffeepos.bridge.service.CashDrawerService;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RestController;

@RestController
@RequestMapping("/cash-drawer")
public class CashDrawerController {

    private final CashDrawerService cashDrawerService;

    public CashDrawerController(CashDrawerService cashDrawerService) {
        this.cashDrawerService = cashDrawerService;
    }

    @PostMapping("/open")
    public ResponseEntity<?> open() {
        try {
            cashDrawerService.open();
            return ResponseEntity.ok().body("{\"status\":\"opened\"}");
        } catch (Exception e) {
            return ResponseEntity.status(502).body("{\"status\":\"error\",\"message\":\"" + e.getMessage() + "\"}");
        }
    }
}
