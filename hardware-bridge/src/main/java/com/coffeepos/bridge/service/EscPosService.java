package com.coffeepos.bridge.service;

import com.coffeepos.bridge.config.BridgeProperties;
import com.coffeepos.bridge.model.ReceiptLine;
import org.springframework.stereotype.Service;

import java.io.ByteArrayOutputStream;
import java.io.IOException;
import java.io.OutputStream;
import java.net.Socket;
import java.nio.charset.StandardCharsets;
import java.util.List;

/**
 * Builds and sends raw ESC/POS byte commands to a thermal receipt/kitchen
 * printer. Hand-rolled rather than pulling in a heavier printing library —
 * ESC/POS is a simple, well-documented byte protocol and this keeps the
 * bridge's dependency footprint small.
 *
 * Network printers (the common case for restaurant thermal printers) are
 * just raw TCP sockets on port 9100 ("JetDirect" / RAW mode) — no driver
 * needed. USB printers would go through javax.usb or the vendor SDK;
 * that integration point is marked below.
 */
@Service
public class EscPosService {

    private static final byte[] INIT = {0x1B, 0x40};                 // ESC @  — initialize printer
    private static final byte[] CUT = {0x1D, 0x56, 0x00};            // GS V 0 — full cut
    private static final byte[] BOLD_ON = {0x1B, 0x45, 0x01};
    private static final byte[] BOLD_OFF = {0x1B, 0x45, 0x00};
    private static final byte[] CENTER = {0x1B, 0x61, 0x01};
    private static final byte[] LEFT = {0x1B, 0x61, 0x00};
    private static final byte[] DOUBLE_HEIGHT_ON = {0x1D, 0x21, 0x11};
    private static final byte[] DOUBLE_HEIGHT_OFF = {0x1D, 0x21, 0x00};
    /** GS p 0 25 250 — pulse pin 2, standard "kick the attached cash drawer" command. */
    private static final byte[] DRAWER_KICK = {0x1D, 0x70, 0x00, 0x19, (byte) 0xFA};

    public void printReceipt(BridgeProperties.PrinterConfig config, String header, List<ReceiptLine> lines,
                              double total, boolean alsoKickDrawer) throws IOException {
        ByteArrayOutputStream buffer = new ByteArrayOutputStream();
        buffer.write(INIT);
        buffer.write(CENTER);
        buffer.write(BOLD_ON);
        write(buffer, header + "\n");
        buffer.write(BOLD_OFF);
        write(buffer, "--------------------------------\n");
        buffer.write(LEFT);

        for (ReceiptLine line : lines) {
            String left = line.quantity() + "x " + line.name();
            String right = String.format("$%.2f", line.lineTotal());
            write(buffer, padLine(left, right, 32) + "\n");
        }

        write(buffer, "--------------------------------\n");
        buffer.write(DOUBLE_HEIGHT_ON);
        write(buffer, padLine("TOTAL", String.format("$%.2f", total), 16) + "\n");
        buffer.write(DOUBLE_HEIGHT_OFF);
        write(buffer, "\n\n");

        if (alsoKickDrawer) {
            buffer.write(DRAWER_KICK);
        }
        buffer.write(CUT);

        send(config, buffer.toByteArray());
    }

    public void kickDrawerOnly(BridgeProperties.PrinterConfig config) throws IOException {
        send(config, DRAWER_KICK);
    }

    private void send(BridgeProperties.PrinterConfig config, byte[] payload) throws IOException {
        switch (config.getConnectionType()) {
            case "network" -> sendOverNetwork(config, payload);
            case "usb" -> throw new UnsupportedOperationException(
                    "USB printing needs the vendor SDK or javax.usb bulk transfer — wire in here.");
            default -> throw new IllegalArgumentException("Unsupported connection type: " + config.getConnectionType());
        }
    }

    private void sendOverNetwork(BridgeProperties.PrinterConfig config, byte[] payload) throws IOException {
        try (Socket socket = new Socket(config.getIpAddress(), config.getPort());
             OutputStream out = socket.getOutputStream()) {
            out.write(payload);
            out.flush();
        }
    }

    private void write(ByteArrayOutputStream buffer, String text) throws IOException {
        buffer.write(text.getBytes(StandardCharsets.UTF_8));
    }

    private String padLine(String left, String right, int width) {
        int spaces = Math.max(1, width - left.length() - right.length());
        return left + " ".repeat(spaces) + right;
    }
}
