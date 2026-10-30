INSERT INTO categories (name, slug, description, is_active)
VALUES
    ('Lomba', 'lomba', 'Kompetisi akademik, hackathon, esai, dan bisnis.', TRUE),
    ('Webinar', 'webinar', 'Kelas daring, lokakarya, dan sesi tanya jawab.', TRUE),
    ('Seminar', 'seminar', 'Seminar, konferensi, dan pertemuan ilmiah.', TRUE)
ON CONFLICT (slug) DO NOTHING;

INSERT INTO categories (name, slug, description, is_active)
VALUES
    ('Teknologi', 'teknologi', 'Teknologi, informatika, rekayasa, dan inovasi digital.', TRUE),
    ('Bisnis', 'bisnis', 'Bisnis, kewirausahaan, dan komersialisasi inovasi.', TRUE),
    ('Akademik', 'akademik', 'Akademik, riset, metodologi, dan pengembangan ilmu.', TRUE),
    ('Umum', 'umum', 'Kegiatan lintas bidang yang tidak masuk topik khusus.', TRUE)
ON CONFLICT (slug) DO UPDATE SET
    name = EXCLUDED.name,
    description = EXCLUDED.description,
    is_active = EXCLUDED.is_active,
    updated_at = now();

UPDATE categories SET is_active = FALSE, updated_at = now()
WHERE slug IN ('lomba', 'webinar', 'seminar');

UPDATE events e
SET event_type = CASE c.slug
        WHEN 'lomba' THEN 'competition'
        WHEN 'webinar' THEN 'webinar'
        WHEN 'seminar' THEN 'seminar'
        ELSE e.event_type
    END,
    category_id = COALESCE(
        (SELECT topic.id FROM categories topic
         WHERE topic.slug = CASE e.slug
             WHEN 'icetisi-2027' THEN 'teknologi'
             WHEN 'hackathon-its-2026' THEN 'teknologi'
             WHEN 'inguide-course-2-webinar-2026' THEN 'akademik'
             WHEN 'youth-innovation-to-market-2026' THEN 'bisnis'
             ELSE 'umum'
         END),
        e.category_id
    )
FROM categories c
WHERE c.id = e.category_id
  AND c.slug IN ('lomba', 'webinar', 'seminar');
