package com.coffeepos.bridge.service;

import com.coffeepos.bridge.config.BridgeProperties;
import com.fazecast.jSerialComm.SerialPort;
import org.springframework.stereotype.Service;

import java.io.IOException;

/**
 * Opens the cash drawer. Two supported wiring modes, configured per-branch
 * in application.yml:
 *  - "printer-kick" (by far the most common): the drawer's RJ11 cable runs
 *    into the receipt printer, and printer opens it via an ESC/POS pulse.
 *  - "serial": a standalone drawer with its own RS232/USB-serial controller.
 */
@Service
public class CashDrawerService {

    private final EscPosService escPos;
    private final BridgeProperties props;

    public CashDrawerService(EscPosService escPos, BridgeProperties props) {
        this.escPos = escPos;
        this.props = props;
    }

    public void open() throws IOException {
        if ("serial".equals(props.getCashDrawer().getTriggerMode())) {
            openViaSerial(props.getCashDrawer().getSerialPort());
        } else {
            escPos.kickDrawerOnly(props.getPrinters().getReceipt());
        }
    }

    private void openViaSerial(String portName) {
        SerialPort port = SerialPort.getCommPort(portName);
        port.setBaudRate(9600);
        if (!port.openPort()) {
            throw new RuntimeException("Could not open serial port " + portName + " for cash drawer.");
        }
        try {
            // Same GS p 0 25 250 pulse, just over a raw serial line instead of
            // through the printer's passthrough port.
            port.getOutputStream().write(new byte[]{0x1D, 0x70, 0x00, 0x19, (byte) 0xFA});
            port.getOutputStream().flush();
        } catch (IOException e) {
            throw new RuntimeException(e);
        } finally {
            port.closePort();
        }
    }
}
