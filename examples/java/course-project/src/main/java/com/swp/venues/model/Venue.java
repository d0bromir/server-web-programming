package com.swp.venues.model;

import jakarta.persistence.*;
import jakarta.validation.constraints.*;

@Entity
public class Venue {

    public static final String[] CATEGORIES =
        {"restaurant", "cafe", "bar", "club", "bakery", "other"};

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @NotBlank(message = "Името е задължително")
    @Size(max = 100)
    private String name;

    @NotBlank(message = "Градът е задължителен")
    @Size(max = 100)
    private String city;

    @NotBlank(message = "Категорията е задължителна")
    private String category;

    @Size(max = 500)
    private String description;

    @Min(value = 1, message = "Рейтингът трябва да е между 1 и 5")
    @Max(value = 5, message = "Рейтингът трябва да е между 1 и 5")
    private double rating;

    private boolean isPublic = true;

    @ManyToOne(fetch = FetchType.LAZY)
    @JoinColumn(name = "user_id")
    private User owner;

    protected Venue() {}

    public Venue(String name, String city, String category,
                 String description, double rating, boolean isPublic, User owner) {
        this.name = name;
        this.city = city;
        this.category = category;
        this.description = description;
        this.rating = rating;
        this.isPublic = isPublic;
        this.owner = owner;
    }

    public Long    getId()          { return id; }
    public String  getName()        { return name; }
    public String  getCity()        { return city; }
    public String  getCategory()    { return category; }
    public String  getDescription() { return description; }
    public double  getRating()      { return rating; }
    public boolean isPublic()       { return isPublic; }
    public User    getOwner()       { return owner; }

    public void setName(String v)        { this.name = v; }
    public void setCity(String v)        { this.city = v; }
    public void setCategory(String v)    { this.category = v; }
    public void setDescription(String v) { this.description = v; }
    public void setRating(double v)      { this.rating = v; }
    public void setPublic(boolean v)     { this.isPublic = v; }
    public void setOwner(User v)         { this.owner = v; }
}
