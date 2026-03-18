package com.swp.frameworks;

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
 * Автоматизирани тестове за Тема 04 – Spring Framework: IoC, DI, Beans, Middleware
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
    void greetWithNameReturns200() throws Exception {
        mvc.perform(get("/demo/greet").param("name", "Студент"))
           .andExpect(status().isOk())
           .andExpect(content().string(org.hamcrest.Matchers.containsString("Студент")));
    }

    @Test
    void beansEndpointReturns200() throws Exception {
        mvc.perform(get("/demo/beans"))
           .andExpect(status().isOk());
    }

    @Test
    void containerEndpointReturns200() throws Exception {
        mvc.perform(get("/demo/container"))
           .andExpect(status().isOk());
    }
}
