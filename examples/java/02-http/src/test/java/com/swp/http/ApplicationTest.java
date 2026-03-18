package com.swp.http;

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
 * Автоматизирани тестове за Тема 02 – HTTP протокол
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
    void homePageReturns200() throws Exception {
        mvc.perform(get("/"))
           .andExpect(status().isOk());
    }

    @Test
    void searchWithQueryParametersReturns200() throws Exception {
        mvc.perform(get("/search").param("q", "java").param("city", "Sofia"))
           .andExpect(status().isOk());
    }

    @Test
    void itemByIdReturns200() throws Exception {
        mvc.perform(get("/items/1"))
           .andExpect(status().isOk());
    }

    @Test
    void itemNotFoundReturns404() throws Exception {
        mvc.perform(get("/items/9999"))
           .andExpect(status().isNotFound());
    }

    @Test
    void addFormReturns200() throws Exception {
        mvc.perform(get("/add"))
           .andExpect(status().isOk());
    }

    @Test
    void addPostRedirects() throws Exception {
        mvc.perform(post("/add")
                .param("name", "TestItem")
                .param("city", "Sofia"))
           .andExpect(status().is3xxRedirection());
    }

    @Test
    void headersPageReturns200() throws Exception {
        mvc.perform(get("/headers"))
           .andExpect(status().isOk());
    }

    @Test
    void oldSearchRedirects301() throws Exception {
        mvc.perform(get("/old-search"))
           .andExpect(status().isMovedPermanently());
    }

    @Test
    void statusCodeEndpointReturnsCorrectStatus() throws Exception {
        mvc.perform(get("/status/418"))
           .andExpect(status().is(418));
    }
}
