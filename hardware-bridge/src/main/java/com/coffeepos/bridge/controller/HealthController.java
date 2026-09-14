package com.coffeepos.bridge.controller;

import com.coffeepos.bridge.config.BridgeProperties;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.RestController;

import java.util.Map;

@RestController
public class HealthController {

    private final BridgeProperties props;

    public HealthController(BridgeProperties props) {
        this.props = props;
    }

    @GetMapping("/health")
    public Map<String, Object> health() {
        return Map.of(
                "status", "up",
                "branchId", props.getBranchId() == null ? "" : props.getBranchId(),
                "receiptPrinter", props.getPrinters().getReceipt().getIpAddress(),
                "scannerMode", props.getScanner().getMode()
        );
    }
}
