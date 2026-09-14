package com.coffeepos.bridge.config;

import com.coffeepos.bridge.websocket.ScannerWebSocketHandler;
import org.springframework.context.annotation.Configuration;
import org.springframework.web.socket.config.annotation.EnableWebSocket;
import org.springframework.web.socket.config.annotation.WebSocketConfigurer;
import org.springframework.web.socket.config.annotation.WebSocketHandlerRegistry;

@Configuration
@EnableWebSocket
public class WebSocketConfig implements WebSocketConfigurer {

    private final ScannerWebSocketHandler scannerHandler;

    public WebSocketConfig(ScannerWebSocketHandler scannerHandler) {
        this.scannerHandler = scannerHandler;
    }

    @Override
    public void registerWebSocketHandlers(WebSocketHandlerRegistry registry) {
        registry.addHandler(scannerHandler, "/ws/scanner").setAllowedOrigins("*");
    }
}
