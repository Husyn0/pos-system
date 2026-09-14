package com.coffeepos.bridge.model;

public record ReceiptLine(int quantity, String name, double lineTotal) {}
