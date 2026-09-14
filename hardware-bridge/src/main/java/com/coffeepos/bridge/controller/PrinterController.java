package com.coffeepos.bridge.controller;

import com.coffeepos.bridge.config.BridgeProperties;
import com.coffeepos.bridge.model.PrintReceiptRequest;
import com.coffeepos.bridge.service.EscPosService;
import jakarta.validation.Valid;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.*;

@RestController
@RequestMapping("/printers")
public class PrinterController {

    private final EscPosService escPos;
    private final BridgeProperties props;

    public PrinterController(EscPosService escPos, BridgeProperties props) {
        this.escPos = escPos;
        this.props = props;
    }

    @PostMapping("/receipt/print")
    public ResponseEntity<?> printReceipt(@Valid @RequestBody PrintReceiptRequest request) {
        try {
            escPos.printReceipt(
                    props.getPrinters().getReceipt(),
                    request.getHeader(),
                    request.getLines(),
                    request.getTotal(),
                    request.isOpenDrawer()
            );
            return ResponseEntity.ok().body("{\"status\":\"printed\"}");
        } catch (Exception e) {
            return ResponseEntity.status(502).body("{\"status\":\"error\",\"message\":\"" + e.getMessage() + "\"}");
        }
    }

    @PostMapping("/kitchen/print")
    public ResponseEntity<?> printKitchenTicket(@Valid @RequestBody PrintReceiptRequest request) {
        try {
            escPos.printReceipt(props.getPrinters().getKitchen(), request.getHeader(), request.getLines(), 0, false);
            return ResponseEntity.ok().body("{\"status\":\"printed\"}");
        } catch (Exception e) {
            return ResponseEntity.status(502).body("{\"status\":\"error\",\"message\":\"" + e.getMessage() + "\"}");
        }
    }
}
