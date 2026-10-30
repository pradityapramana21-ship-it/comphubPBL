INSERT INTO categories (name, slug, description, is_active)
VALUES
    ('Lomba', 'lomba', 'Kompetisi akademik, hackathon, esai, dan bisnis.', TRUE),
    ('Webinar', 'webinar', 'Kelas daring, lokakarya, dan sesi tanya jawab.', TRUE),
    ('Seminar', 'seminar', 'Seminar, konferensi, dan pertemuan ilmiah.', TRUE)
ON CONFLICT (slug) DO NOTHING;
