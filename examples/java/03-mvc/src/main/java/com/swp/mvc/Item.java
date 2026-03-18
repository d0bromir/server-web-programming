package com.swp.mvc;

/**
 * Model – представлява данните (заведение).
 * Java record автоматично генерира constructor, getters, equals, hashCode, toString.
 */
public record Item(int id, String name, String city, String category) {}
