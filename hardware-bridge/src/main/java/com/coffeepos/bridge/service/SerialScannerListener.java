package com.coffeepos.bridge.service;

import com.coffeepos.bridge.config.BridgeProperties;
import com.coffeepos.bridge.websocket.ScannerWebSocketHandler;
import com.fazecast.jSerialComm.SerialPort;
import jakarta.annotation.PostConstruct;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.stereotype.Service;

import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.nio.charset.StandardCharsets;

/**
 * Only activates when bridge.scanner.mode = serial (application.yml).
 * Most USB barcode scanners are "keyboard wedge" and need no code at all —
 * this exists for serial-interface scanners on self-checkout kiosks where
 * you don't want a scan to land in whatever text field happens to have
 * focus.
 */
@Service
public class SerialScannerListener {

    private static final Logger log = LoggerFactory.getLogger(SerialScannerListener.class);

    private final BridgeProperties props;
    private final ScannerWebSocketHandler broadcaster;

    public SerialScannerListener(BridgeProperties props, ScannerWebSocketHandler broadcaster) {
        this.props = props;
        this.broadcaster = broadcaster;
    }

    @PostConstruct
    public void start() {
        if (!"serial".equals(props.getScanner().getMode())) {
            return;
        }
        Thread thread = new Thread(this::listenLoop, "serial-scanner-listener");
        thread.setDaemon(true);
        thread.start();
    }

    private void listenLoop() {
        String portName = props.getScanner().getSerialPort();
        SerialPort port = SerialPort.getCommPort(portName);
        port.setBaudRate(9600);

        if (!port.openPort()) {
            log.warn("Could not open serial scanner port {} — scanner integration disabled.", portName);
            return;
        }

        try (BufferedReader reader = new BufferedReader(
                new InputStreamReader(port.getInputStream(), StandardCharsets.UTF_8))) {
            String line;
            while ((line = reader.readLine()) != null) {
                if (!line.isBlank()) {
                    broadcaster.broadcastScan(line.trim());
                }
            }
        } catch (Exception e) {
            log.error("Serial scanner listener stopped unexpectedly", e);
        } finally {
            port.closePort();
        }
    }
}
