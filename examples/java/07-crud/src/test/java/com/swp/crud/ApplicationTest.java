package com.swp.crud;

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
 * Автоматизирани тестове за Тема 07 – CRUD операции с Spring Boot + Thymeleaf
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
    void venuesListReturns200() throws Exception {
        mvc.perform(get("/venues"))
           .andExpect(status().isOk())
           .andExpect(content().contentTypeCompatibleWith("text/html"));
    }

    @Test
    void newVenueFormReturns200() throws Exception {
        mvc.perform(get("/venues/new"))
           .andExpect(status().isOk());
    }

    @Test
    void createVenuePostRedirects() throws Exception {
        mvc.perform(post("/venues")
                .param("name", "Тест Кафе")
                .param("city", "Тест Град")
                .param("category", "cafe")
                .param("rating", "4"))
           .andExpect(status().is3xxRedirection())
           .andExpect(header().string("Location", "/venues"));
    }

    @Test
    void createVenueWithEmptyNameShowsFormErrors() throws Exception {
        mvc.perform(post("/venues")
                .param("name", "")          // задължително поле
                .param("city", "София")
                .param("category", "cafe")
                .param("rating", "4"))
           .andExpect(status().isOk())      // форма се показва отново
           .andExpect(content().contentTypeCompatibleWith("text/html"));
    }

    @Test
    void editVenueFormReturns200() throws Exception {
        // Първо създаваме venue за да го редактираме
        mvc.perform(post("/venues")
                .param("name", "За Редакция")
                .param("city", "София")
                .param("category", "bar")
                .param("rating", "3"));

        mvc.perform(get("/venues/1/edit"))
           .andExpect(status().isOk());
    }

    @Test
    void updateVenuePostRedirects() throws Exception {
        mvc.perform(post("/venues/1")
                .param("name", "Обновено Кафе")
                .param("city", "София")
                .param("category", "cafe")
                .param("rating", "5"))
           .andExpect(status().is3xxRedirection());
    }

    @Test
    void deleteVenuePostRedirects() throws Exception {
        // Изтриваме заредено от data.sql заведение (ID 3)
        mvc.perform(post("/venues/3/delete"))
           .andExpect(status().is3xxRedirection());
    }
}
