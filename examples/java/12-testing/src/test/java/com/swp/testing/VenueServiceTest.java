package com.swp.testing;

import org.junit.jupiter.api.*;
import org.junit.jupiter.api.extension.ExtendWith;
import org.mockito.*;
import org.mockito.junit.jupiter.MockitoExtension;

import java.util.List;
import java.util.Optional;

import static org.assertj.core.api.Assertions.*;
import static org.mockito.Mockito.*;

/**
 * Unit тест на VenueService с Mockito.
 * VenueRepository е Mock – без реална БД.
 *
 * @ExtendWith(MockitoExtension.class) – инициализира @Mock и @InjectMocks
 */
@ExtendWith(MockitoExtension.class)
class VenueServiceTest {

    @Mock
    VenueRepository repo;

    @InjectMocks
    VenueService service;

    @Test
    void findAll_returnsAllVenues() {
        List<Venue> venues = List.of(
            new Venue("Кафе А", "София",  4.5),
            new Venue("Ресторант Б", "Варна", 4.0)
        );
        when(repo.findAll()).thenReturn(venues);

        List<Venue> result = service.findAll();

        assertThat(result).hasSize(2);
        assertThat(result.get(0).getName()).isEqualTo("Кафе А");
        verify(repo, times(1)).findAll();
    }

    @Test
    void findById_existingId_returnsVenue() {
        Venue v = new Venue("Кафе А", "София", 4.5);
        when(repo.findById(1L)).thenReturn(Optional.of(v));

        Optional<Venue> result = service.findById(1L);

        assertThat(result).isPresent();
        assertThat(result.get().getName()).isEqualTo("Кафе А");
    }

    @Test
    void findById_missingId_returnsEmpty() {
        when(repo.findById(99L)).thenReturn(Optional.empty());

        assertThat(service.findById(99L)).isEmpty();
    }

    @Test
    void create_validData_savesAndReturns() {
        Venue saved = new Venue("Ново Кафе", "Пловдив", 4.2);
        when(repo.save(any(Venue.class))).thenReturn(saved);

        Venue result = service.create("Ново Кафе", "Пловдив", 4.2);

        assertThat(result.getName()).isEqualTo("Ново Кафе");
        verify(repo, times(1)).save(any(Venue.class));
    }

    @Test
    void create_blankName_throwsException() {
        assertThatThrownBy(() -> service.create("", "Пловдив", 4.0))
            .isInstanceOf(IllegalArgumentException.class)
            .hasMessageContaining("Името");
    }

    @Test
    void create_invalidRating_throwsException() {
        assertThatThrownBy(() -> service.create("Кафе", "София", 6.0))
            .isInstanceOf(IllegalArgumentException.class)
            .hasMessageContaining("Рейтингът");
    }

    @Test
    void delete_callsRepository() {
        service.delete(5L);
        verify(repo, times(1)).deleteById(5L);
    }
}
