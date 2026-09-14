package com.coffeepos.bridge.websocket;

import org.springframework.stereotype.Component;
import org.springframework.web.socket.TextMessage;
import org.springframework.web.socket.WebSocketSession;
import org.springframework.web.socket.handler.TextWebSocketHandler;

import java.io.IOException;
import java.util.Set;
import java.util.concurrent.CopyOnWriteArraySet;

/**
 * Pushes barcode scans out to whatever's listening locally (the desktop
 * POS app, or admin-web's POS screen if it's running on the same machine
 * as the bridge). Keyboard-wedge scanners don't need this at all — they
 * just "type" into the focused field — this path is only for serial-mode
 * scanners on kiosks/dedicated stations.
 */
@Component
public class ScannerWebSocketHandler extends TextWebSocketHandler {

    private final Set<WebSocketSession> sessions = new CopyOnWriteArraySet<>();

    @Override
    public void afterConnectionEstablished(WebSocketSession session) {
        sessions.add(session);
    }

    @Override
    public void afterConnectionClosed(WebSocketSession session, org.springframework.web.socket.CloseStatus status) {
        sessions.remove(session);
    }

    public void broadcastScan(String barcode) {
        TextMessage message = new TextMessage("{\"type\":\"barcode_scanned\",\"code\":\"" + barcode + "\"}");
        for (WebSocketSession session : sessions) {
            try {
                if (session.isOpen()) session.sendMessage(message);
            } catch (IOException ignored) {
                // a dead session will be cleaned up on its own close callback
            }
        }
    }
}
