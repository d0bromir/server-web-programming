package com.swp.introduction;

import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.boot.test.context.SpringBootTest;
import org.springframework.test.web.servlet.MockMvc;
import org.springframework.test.web.servlet.setup.MockMvcBuilders;
import org.springframework.web.context.WebApplicationContext;

import static org.springframework.test.web.servlet.request.MockMvcRequestBuilders.get;
import static org.springframework.test.web.servlet.result.MockMvcResultMatchers.*;

/**
 * Автоматизирани тестове за Тема 01 – Въведение в сървърното уеб програмиране
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
           .andExpect(status().isOk())
           .andExpect(content().contentTypeCompatibleWith("text/html"));
    }

    @Test
    void greetWithNameParameterReturns200() throws Exception {
        mvc.perform(get("/greet").param("name", "Студент"))
           .andExpect(status().isOk())
           .andExpect(content().string(org.hamcrest.Matchers.containsString("Студент")));
    }

    @Test
    void aboutPageReturns200() throws Exception {
        mvc.perform(get("/about"))
           .andExpect(status().isOk());
    }
}
