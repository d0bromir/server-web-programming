package com.swp.testing;

import org.junit.jupiter.api.Test;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.boot.test.autoconfigure.web.servlet.WebMvcTest;
import org.springframework.test.context.bean.override.mockito.MockitoBean;
import org.springframework.test.web.servlet.MockMvc;

import java.util.List;
import java.util.Optional;

import static org.mockito.Mockito.*;
import static org.springframework.test.web.servlet.request.MockMvcRequestBuilders.get;
import static org.springframework.test.web.servlet.result.MockMvcResultMatchers.*;

/**
 * Интеграционен тест на Controller слоя с MockMvc.
 *
 * @WebMvcTest зарежда само web слоя (без JPA, без Service).
 * VenueService е Mock – @MockitoBean (Spring Boot 3.4+).
 */
@WebMvcTest(VenueController.class)
class VenueControllerTest {

    @Autowired
    MockMvc mockMvc;

    @MockitoBean
    VenueService venueService;

    @Test
    void listPage_returns200AndVenueList() throws Exception {
        List<Venue> venues = List.of(
            new Venue("Кафе А", "София", 4.5),
            new Venue("Ресторант Б", "Варна", 4.0)
        );
        when(venueService.findAll()).thenReturn(venues);

        mockMvc.perform(get("/venues"))
            .andExpect(status().isOk())
            .andExpect(view().name("venues/list"))
            .andExpect(model().attributeExists("venues"));
    }

    @Test
    void detailPage_existingId_returns200() throws Exception {
        Venue v = new Venue("Кафе А", "София", 4.5);
        when(venueService.findById(1L)).thenReturn(Optional.of(v));

        mockMvc.perform(get("/venues/1"))
            .andExpect(status().isOk())
            .andExpect(view().name("venues/detail"))
            .andExpect(model().attributeExists("venue"));
    }

    @Test
    void detailPage_missingId_redirectsToList() throws Exception {
        when(venueService.findById(99L)).thenReturn(Optional.empty());

        mockMvc.perform(get("/venues/99"))
            .andExpect(status().is3xxRedirection())
            .andExpect(redirectedUrl("/venues"));
    }
}
