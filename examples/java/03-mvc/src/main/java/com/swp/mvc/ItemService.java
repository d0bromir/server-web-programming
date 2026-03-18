package com.swp.mvc;

import org.springframework.stereotype.Service;
import java.util.*;

/**
 * Service / Repository – бизнес логика и достъп до данни.
 * В тема 06 ще използваме Spring Data JPA. Тук – in-memory списък.
 */
@Service
public class ItemService {

    private final List<Item> store = new ArrayList<>(List.of(
        new Item(1, "Ресторант Централ", "София",   "restaurant"),
        new Item(2, "Кафе Витоша",       "София",   "cafe"),
        new Item(3, "Пивоварна Загорка", "Стара Загора", "bar"),
        new Item(4, "Пицария Наполи",    "Пловдив", "restaurant")
    ));

    public List<Item> findAll() {
        return Collections.unmodifiableList(store);
    }

    public Optional<Item> findById(int id) {
        return store.stream().filter(i -> i.id() == id).findFirst();
    }

    public List<Item> findByCategory(String category) {
        return store.stream()
            .filter(i -> i.category().equalsIgnoreCase(category))
            .toList();
    }
}
