package com.coffeepos.bridge.model;

import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.NotEmpty;

import java.util.List;

public class PrintReceiptRequest {
    @NotBlank
    private String header = "Brewline Coffee";

    @NotEmpty
    private List<ReceiptLine> lines;

    private double total;

    /** Most sites keep the drawer wired through the receipt printer, so a
     *  cash sale can print + open the drawer in one call. */
    private boolean openDrawer = false;

    public String getHeader() { return header; }
    public void setHeader(String header) { this.header = header; }
    public List<ReceiptLine> getLines() { return lines; }
    public void setLines(List<ReceiptLine> lines) { this.lines = lines; }
    public double getTotal() { return total; }
    public void setTotal(double total) { this.total = total; }
    public boolean isOpenDrawer() { return openDrawer; }
    public void setOpenDrawer(boolean openDrawer) { this.openDrawer = openDrawer; }
}
