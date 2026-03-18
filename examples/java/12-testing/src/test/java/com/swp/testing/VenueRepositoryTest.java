package com.swp.testing;

import org.junit.jupiter.api.Test;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.boot.test.autoconfigure.orm.jpa.DataJpaTest;

import java.util.List;

import static org.assertj.core.api.Assertions.assertThat;

/**
 * JPA тест с @DataJpaTest.
 * Зарежда само JPA слоя с вградена H2 БД.
 * Всеки тест се изпълнява в транзакция, която се връща назад.
 */
@DataJpaTest
class VenueRepositoryTest {

    @Autowired
    VenueRepository repo;

    @Test
    void saveAndFindById() {
        Venue saved = repo.save(new Venue("Тест Кафе", "София", 4.3));

        assertThat(saved.getId()).isNotNull();
        assertThat(repo.findById(saved.getId())).isPresent();
    }

    @Test
    void findByCity_returnsMatchingVenues() {
        repo.save(new Venue("Хаджи Дарио", "Варна",  4.1));
        repo.save(new Venue("Морска Градина", "Варна", 4.6));
        repo.save(new Venue("Кафе Централ",  "София", 4.0));

        List<Venue> varnaVenues = repo.findByCity("Варна");

        assertThat(varnaVenues).hasSize(2);
        assertThat(varnaVenues).allMatch(v -> v.getCity().equals("Варна"));
    }

    @Test
    void findByRatingGreaterThanEqual_filtersCorrectly() {
        repo.save(new Venue("Добро",    "Пловдив", 4.5));
        repo.save(new Venue("Средно",   "Пловдив", 3.5));
        repo.save(new Venue("Отлично",  "Пловдив", 5.0));

        List<Venue> topVenues = repo.findByRatingGreaterThanEqual(4.5);

        assertThat(topVenues).hasSize(2);
        assertThat(topVenues).allMatch(v -> v.getRating() >= 4.5);
    }

    @Test
    void deleteById_removesVenue() {
        Venue v = repo.save(new Venue("За изтриване", "Бургас", 3.0));
        Long id = v.getId();

        repo.deleteById(id);

        assertThat(repo.findById(id)).isEmpty();
    }

    @Test
    void count_reflectsCorrectNumber() {
        repo.save(new Venue("А", "София", 4.0));
        repo.save(new Venue("Б", "Варна",  4.0));

        assertThat(repo.count()).isEqualTo(2);
    }
}
