package com.coffeepos.bridge.config;

import jakarta.servlet.FilterChain;
import jakarta.servlet.ServletException;
import jakarta.servlet.http.HttpServletRequest;
import jakarta.servlet.http.HttpServletResponse;
import org.springframework.boot.web.servlet.FilterRegistrationBean;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;
import org.springframework.web.filter.OncePerRequestFilter;

import java.io.IOException;

/**
 * This bridge deliberately has no complex auth system (it's LAN-only,
 * paired with a shared secret set at install time) — see docs/SECURITY.md
 * for why this is an acceptable tradeoff for an on-premise hardware
 * controller versus the cloud API's full Sanctum/RBAC model.
 */
@Configuration
public class SecurityConfig {

    @Bean
    public FilterRegistrationBean<OncePerRequestFilter> sharedSecretFilter(BridgeProperties props) {
        FilterRegistrationBean<OncePerRequestFilter> registration = new FilterRegistrationBean<>();
        registration.setFilter(new OncePerRequestFilter() {
            @Override
            protected void doFilterInternal(HttpServletRequest request, HttpServletResponse response, FilterChain chain)
                    throws ServletException, IOException {
                if (request.getRequestURI().equals("/health")) {
                    chain.doFilter(request, response);
                    return;
                }
                String provided = request.getHeader("X-Bridge-Secret");
                if (provided == null || !provided.equals(props.getSharedSecret())) {
                    response.sendError(HttpServletResponse.SC_UNAUTHORIZED, "Invalid bridge secret");
                    return;
                }
                chain.doFilter(request, response);
            }
        });
        registration.addUrlPatterns("/*");
        return registration;
    }
}
