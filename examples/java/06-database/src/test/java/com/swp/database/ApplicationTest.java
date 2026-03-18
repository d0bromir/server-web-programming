package com.swp.database;

import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.boot.test.context.SpringBootTest;
import org.springframework.http.MediaType;
import org.springframework.test.web.servlet.MockMvc;
import org.springframework.test.web.servlet.setup.MockMvcBuilders;
import org.springframework.web.context.WebApplicationContext;

import static org.springframework.test.web.servlet.request.MockMvcRequestBuilders.get;
import static org.springframework.test.web.servlet.result.MockMvcResultMatchers.*;

/**
 * Автоматизирани тестове за Тема 06 – База данни и ORM (Spring Data JPA + H2)
 *
 * Стартиране:
 *   mvn test
 */
@SpringBootTest
class ApplicationTest {

    @Autowired
    WebApplicationContext wac;

    MockMvc mvc;

    @BeforeEach
    void setUp() {
        mvc = MockMvcBuilders.webAppContextSetup(wac).build();
    }

    @Test
    void allVenuesReturns200() throws Exception {
        mvc.perform(get("/api/venues").accept(MediaType.APPLICATION_JSON))
           .andExpect(status().isOk());
    }

    @Test
    void paginationReturns200() throws Exception {
        mvc.perform(get("/api/venues").param("page", "0").param("size", "2"))
           .andExpect(status().isOk());
    }

    @Test
    void filterByCityReturns200() throws Exception {
        mvc.perform(get("/api/venues").param("city", "Sofia"))
           .andExpect(status().isOk());
    }

    @Test
    void singleVenueReturns200OrNotFound() throws Exception {
        // Примерни данни се зареждат при стартиране; ID 1 трябва да съществува
        mvc.perform(get("/api/venues/1"))
           .andExpect(status().is2xxSuccessful());
    }

    @Test
    void nonExistentVenueReturns404() throws Exception {
        mvc.perform(get("/api/venues/999999"))
           .andExpect(status().isNotFound());
    }
}
