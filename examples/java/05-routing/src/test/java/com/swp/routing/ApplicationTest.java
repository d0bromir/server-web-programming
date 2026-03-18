package com.swp.routing;

import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.boot.test.context.SpringBootTest;
import org.springframework.test.web.servlet.MockMvc;
import org.springframework.test.web.servlet.setup.MockMvcBuilders;
import org.springframework.web.context.WebApplicationContext;

import static org.springframework.test.web.servlet.request.MockMvcRequestBuilders.*;
import static org.springframework.test.web.servlet.result.MockMvcResultMatchers.*;

/**
 * Автоматизирани тестове за Тема 05 – Маршрутизиране в Spring Boot
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
        mvc.perform(get("/venues"))
           .andExpect(status().isOk());
    }

    @Test
    void searchWithQueryParamsReturns200() throws Exception {
        mvc.perform(get("/venues").param("search", "sofia").param("page", "0").param("size", "2"))
           .andExpect(status().isOk());
    }

    @Test
    void singleVenueReturns200() throws Exception {
        mvc.perform(get("/venues/1"))
           .andExpect(status().isOk());
    }

    @Test
    void unknownVenueReturns404() throws Exception {
        mvc.perform(get("/venues/9999"))
           .andExpect(status().isNotFound());
    }
}
