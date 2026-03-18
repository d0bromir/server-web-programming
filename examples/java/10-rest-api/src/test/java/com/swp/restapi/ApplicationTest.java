package com.swp.restapi;

import jakarta.servlet.Filter;
import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.boot.test.context.SpringBootTest;
import org.springframework.http.MediaType;
import org.springframework.test.web.servlet.MockMvc;
import org.springframework.test.web.servlet.setup.MockMvcBuilders;
import org.springframework.web.context.WebApplicationContext;

import static org.springframework.test.web.servlet.request.MockMvcRequestBuilders.*;
import static org.springframework.test.web.servlet.result.MockMvcResultMatchers.*;

@SpringBootTest
class ApplicationTest {

    @Autowired
    WebApplicationContext wac;

    @Autowired
    Filter apiTokenFilter;

    MockMvc mvc;

    @BeforeEach
    void setUp() {
        mvc = MockMvcBuilders.webAppContextSetup(wac)
                .addFilters(apiTokenFilter)
                .build();
    }

    // ── Информационни ендпойнти ────────────────────────────────────────

    @Test
    void infoReturnsOk() throws Exception {
        mvc.perform(get("/api/info"))
                .andExpect(status().isOk())
                .andExpect(content().contentTypeCompatibleWith(MediaType.APPLICATION_JSON));
    }

    @Test
    void helloWithNameReturnsOk() throws Exception {
        mvc.perform(get("/api/hello").param("name", "Студент"))
                .andExpect(status().isOk());
    }

    @Test
    void requestInfoReturnsOk() throws Exception {
        mvc.perform(get("/api/request-info"))
                .andExpect(status().isOk());
    }

    @Test
    void headersReturnsOk() throws Exception {
        mvc.perform(get("/api/headers"))
                .andExpect(status().isOk());
    }

    @Test
    void statusEndpointReturns418() throws Exception {
        mvc.perform(get("/api/status/418"))
                .andExpect(status().isIAmATeapot());
    }

    @Test
    void statusEndpointReturns404() throws Exception {
        mvc.perform(get("/api/status/404"))
                .andExpect(status().isNotFound());
    }

    @Test
    void oldVenuesRedirects() throws Exception {
        mvc.perform(get("/api/old-venues"))
                .andExpect(status().isMovedPermanently());
    }

    // ── Публичен GET /api/venues (без токен) ───────────────────────────

    @Test
    void listVenuesNoTokenReturnsOk() throws Exception {
        mvc.perform(get("/api/venues"))
                .andExpect(status().isOk())
                .andExpect(content().contentTypeCompatibleWith(MediaType.APPLICATION_JSON));
    }

    @Test
    void listVenuesWithPaginationReturnsOk() throws Exception {
        mvc.perform(get("/api/venues").param("page", "0").param("size", "2"))
                .andExpect(status().isOk());
    }

    @Test
    void getVenueByIdReturnsOk() throws Exception {
        mvc.perform(get("/api/venues/1"))
                .andExpect(status().isOk());
    }

    @Test
    void getVenueByMissingIdReturns404() throws Exception {
        mvc.perform(get("/api/venues/999999"))
                .andExpect(status().isNotFound());
    }

    // ── POST изисква токен ─────────────────────────────────────────────

    @Test
    void createVenueWithoutTokenReturns401() throws Exception {
        mvc.perform(post("/api/venues")
                        .contentType(MediaType.APPLICATION_JSON)
                        .content("{\"name\":\"Тест\",\"city\":\"София\",\"category\":\"Bar\",\"rating\":4.5}"))
                .andExpect(status().isUnauthorized());
    }

    @Test
    void createVenueWithTokenReturnsCreated() throws Exception {
        mvc.perform(post("/api/venues")
                        .header("Authorization", "Bearer secret-token")
                        .contentType(MediaType.APPLICATION_JSON)
                        .content("{\"name\":\"Тест\",\"city\":\"София\",\"category\":\"Bar\",\"rating\":4.5}"))
                .andExpect(status().isCreated());
    }

    @Test
    void createVenueWithInvalidTokenReturns401() throws Exception {
        mvc.perform(post("/api/venues")
                        .header("Authorization", "Bearer wrong-token")
                        .contentType(MediaType.APPLICATION_JSON)
                        .content("{\"name\":\"Тест\",\"city\":\"София\",\"category\":\"Bar\",\"rating\":4.5}"))
                .andExpect(status().isUnauthorized());
    }

    // ── PUT изисква токен ──────────────────────────────────────────────

    @Test
    void updateVenueWithoutTokenReturns401() throws Exception {
        mvc.perform(put("/api/venues/1")
                        .contentType(MediaType.APPLICATION_JSON)
                        .content("{\"name\":\"Обновено\",\"city\":\"Пловдив\",\"category\":\"Club\",\"rating\":4.5}"))
                .andExpect(status().isUnauthorized());
    }

    @Test
    void updateVenueWithTokenReturnsOk() throws Exception {
        mvc.perform(put("/api/venues/1")
                        .header("Authorization", "Bearer secret-token")
                        .contentType(MediaType.APPLICATION_JSON)
                        .content("{\"name\":\"Обновено\",\"city\":\"Пловдив\",\"category\":\"Club\",\"rating\":4.5}"))
                .andExpect(status().isOk());
    }

    // ── PATCH изисква токен ────────────────────────────────────────────

    @Test
    void patchVenueWithoutTokenReturns401() throws Exception {
        mvc.perform(patch("/api/venues/1")
                        .contentType(MediaType.APPLICATION_JSON)
                        .content("{\"city\":\"Варна\"}"))
                .andExpect(status().isUnauthorized());
    }

    @Test
    void patchVenueWithTokenReturnsOk() throws Exception {
        mvc.perform(patch("/api/venues/1")
                        .header("Authorization", "Bearer secret-token")
                        .contentType(MediaType.APPLICATION_JSON)
                        .content("{\"city\":\"Варна\"}"))
                .andExpect(status().isOk());
    }

    // ── DELETE изисква токен ───────────────────────────────────────────

    @Test
    void deleteVenueWithoutTokenReturns401() throws Exception {
        mvc.perform(delete("/api/venues/2"))
                .andExpect(status().isUnauthorized());
    }

    @Test
    void deleteVenueWithTokenReturnsOk() throws Exception {
        mvc.perform(delete("/api/venues/2")
                        .header("Authorization", "Bearer secret-token"))
                .andExpect(status().isOk());
    }
}
