package com.coffeepos.bridge.config;

import org.springframework.boot.context.properties.ConfigurationProperties;
import org.springframework.stereotype.Component;

@Component
@ConfigurationProperties(prefix = "bridge")
public class BridgeProperties {

    private String sharedSecret;
    private String branchId;
    private String cloudApiUrl;
    private Printers printers = new Printers();
    private CashDrawer cashDrawer = new CashDrawer();
    private Scanner scanner = new Scanner();

    public static class Printers {
        private PrinterConfig receipt = new PrinterConfig();
        private PrinterConfig kitchen = new PrinterConfig();
        public PrinterConfig getReceipt() { return receipt; }
        public void setReceipt(PrinterConfig receipt) { this.receipt = receipt; }
        public PrinterConfig getKitchen() { return kitchen; }
        public void setKitchen(PrinterConfig kitchen) { this.kitchen = kitchen; }
    }

    public static class PrinterConfig {
        private String connectionType = "network";
        private String ipAddress;
        private int port = 9100;
        private int paperWidthMm = 80;
        private boolean hasCashDrawer = false;

        public String getConnectionType() { return connectionType; }
        public void setConnectionType(String connectionType) { this.connectionType = connectionType; }
        public String getIpAddress() { return ipAddress; }
        public void setIpAddress(String ipAddress) { this.ipAddress = ipAddress; }
        public int getPort() { return port; }
        public void setPort(int port) { this.port = port; }
        public int getPaperWidthMm() { return paperWidthMm; }
        public void setPaperWidthMm(int paperWidthMm) { this.paperWidthMm = paperWidthMm; }
        public boolean isHasCashDrawer() { return hasCashDrawer; }
        public void setHasCashDrawer(boolean hasCashDrawer) { this.hasCashDrawer = hasCashDrawer; }
    }

    public static class CashDrawer {
        private String triggerMode = "printer-kick";
        private String serialPort;
        public String getTriggerMode() { return triggerMode; }
        public void setTriggerMode(String triggerMode) { this.triggerMode = triggerMode; }
        public String getSerialPort() { return serialPort; }
        public void setSerialPort(String serialPort) { this.serialPort = serialPort; }
    }

    public static class Scanner {
        private String mode = "keyboard-wedge";
        private String serialPort;
        public String getMode() { return mode; }
        public void setMode(String mode) { this.mode = mode; }
        public String getSerialPort() { return serialPort; }
        public void setSerialPort(String serialPort) { this.serialPort = serialPort; }
    }

    public String getSharedSecret() { return sharedSecret; }
    public void setSharedSecret(String sharedSecret) { this.sharedSecret = sharedSecret; }
    public String getBranchId() { return branchId; }
    public void setBranchId(String branchId) { this.branchId = branchId; }
    public String getCloudApiUrl() { return cloudApiUrl; }
    public void setCloudApiUrl(String cloudApiUrl) { this.cloudApiUrl = cloudApiUrl; }
    public Printers getPrinters() { return printers; }
    public void setPrinters(Printers printers) { this.printers = printers; }
    public CashDrawer getCashDrawer() { return cashDrawer; }
    public void setCashDrawer(CashDrawer cashDrawer) { this.cashDrawer = cashDrawer; }
    public Scanner getScanner() { return scanner; }
    public void setScanner(Scanner scanner) { this.scanner = scanner; }
}
