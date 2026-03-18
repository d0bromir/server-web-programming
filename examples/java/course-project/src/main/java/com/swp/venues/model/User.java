package com.swp.venues.model;

import jakarta.persistence.*;
import jakarta.validation.constraints.*;

@Entity
@Table(name = "app_user")
public class User {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @Column(nullable = false)
    private String name;

    @Email
    @Column(unique = true, nullable = false)
    private String email;

    @Column(nullable = false)
    private String password;

    @Column(nullable = false)
    private String role = "ROLE_USER";   // ROLE_USER | ROLE_ADMIN

    /** Bearer токен за REST API автентикация */
    @Column(unique = true)
    private String apiToken;

    protected User() {}

    public User(String name, String email, String password, String role, String apiToken) {
        this.name = name;
        this.email = email;
        this.password = password;
        this.role = role;
        this.apiToken = apiToken;
    }

    public Long   getId()       { return id; }
    public String getName()     { return name; }
    public String getEmail()    { return email; }
    public String getPassword() { return password; }
    public String getRole()     { return role; }
    public String getApiToken() { return apiToken; }

    public void setPassword(String p) { this.password = p; }
    public void setApiToken(String t) { this.apiToken = t; }
}
