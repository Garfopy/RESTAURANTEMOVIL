-- ============================================================
-- Migration 043: Sincroniza rest_receta_ingredientes en capirest
-- con el contenido de idactivos_carnihubdb (snapshot 2026-05-19)
-- MySQL 5.7 / restaurante_id = 1 (La Comalada)
--
-- Reglas:
--  * En rest_ingredientes SOLO van códigos G* (guarniciones) y MP*
--    (materias primas). Los platillos B* (bebidas) y DP* (postres)
--    NO llevan ingredientes propios.
--  * Se vacía y repuebla rest_receta_ingredientes con los vínculos
--    M*-G* y M*-MP* traducidos al espacio de IDs de capirest.
--
-- Mapeo de IDs:
--    ingrediente_id carnihub (47-102, G1-G21+MP1-MP35) -> capirest (id-25)
--    receta_id     carnihub (2-36,  M1-M35)            -> capirest (id-1)
--    receta_id     carnihub (37-88, DP1-DP18+B1-B34)  -> capirest (id+34)
--
-- Filas omitidas (31): apuntan a recetas o ingredientes
-- inexistentes en capirest (sample stock, restaurante 2, o self-MP de bebidas).
--   src id=15  receta=1  ingr=4
--   src id=16  receta=1  ingr=10
--   src id=17  receta=1  ingr=11
--   src id=18  receta=1  ingr=6
--   src id=378  receta=75  ingr=18
--   src id=379  receta=130  ingr=19
--   src id=380  receta=131  ingr=20
--   src id=381  receta=132  ingr=21
--   src id=382  receta=133  ingr=22
--   src id=383  receta=134  ingr=41
--   src id=384  receta=63  ingr=130
--   src id=385  receta=64  ingr=131
--   src id=386  receta=65  ingr=132
--   src id=387  receta=66  ingr=133
--   src id=388  receta=67  ingr=134
--   src id=389  receta=68  ingr=135
--   src id=390  receta=69  ingr=136
--   src id=391  receta=70  ingr=137
--   src id=392  receta=71  ingr=138
--   src id=393  receta=72  ingr=139
--   src id=394  receta=73  ingr=140
--   src id=395  receta=74  ingr=141
--   src id=396  receta=75  ingr=142
--   src id=397  receta=76  ingr=143
--   src id=398  receta=77  ingr=144
--   src id=399  receta=78  ingr=145
--   src id=400  receta=79  ingr=146
--   src id=401  receta=80  ingr=147
--   src id=402  receta=81  ingr=148
--   src id=403  receta=82  ingr=149
--   src id=404  receta=83  ingr=150
-- ============================================================

START TRANSACTION;

SET @rest_id = 1;

-- Capturar IDs por codigo (G1-G21, MP1-MP35) — agnóstico a AUTO_INCREMENT
SET @i_g1 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='G1' LIMIT 1);
SET @i_g2 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='G2' LIMIT 1);
SET @i_g3 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='G3' LIMIT 1);
SET @i_g4 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='G4' LIMIT 1);
SET @i_g5 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='G5' LIMIT 1);
SET @i_g6 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='G6' LIMIT 1);
SET @i_g7 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='G7' LIMIT 1);
SET @i_g8 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='G8' LIMIT 1);
SET @i_g9 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='G9' LIMIT 1);
SET @i_g10 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='G10' LIMIT 1);
SET @i_g11 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='G11' LIMIT 1);
SET @i_g12 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='G12' LIMIT 1);
SET @i_g13 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='G13' LIMIT 1);
SET @i_g14 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='G14' LIMIT 1);
SET @i_g15 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='G15' LIMIT 1);
SET @i_g16 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='G16' LIMIT 1);
SET @i_g17 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='G17' LIMIT 1);
SET @i_g18 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='G18' LIMIT 1);
SET @i_g19 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='G19' LIMIT 1);
SET @i_g20 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='G20' LIMIT 1);
SET @i_g21 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='G21' LIMIT 1);
SET @i_mp1 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP1' LIMIT 1);
SET @i_mp2 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP2' LIMIT 1);
SET @i_mp3 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP3' LIMIT 1);
SET @i_mp4 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP4' LIMIT 1);
SET @i_mp5 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP5' LIMIT 1);
SET @i_mp6 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP6' LIMIT 1);
SET @i_mp7 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP7' LIMIT 1);
SET @i_mp8 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP8' LIMIT 1);
SET @i_mp9 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP9' LIMIT 1);
SET @i_mp10 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP10' LIMIT 1);
SET @i_mp11 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP11' LIMIT 1);
SET @i_mp12 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP12' LIMIT 1);
SET @i_mp13 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP13' LIMIT 1);
SET @i_mp14 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP14' LIMIT 1);
SET @i_mp15 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP15' LIMIT 1);
SET @i_mp16 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP16' LIMIT 1);
SET @i_mp17 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP17' LIMIT 1);
SET @i_mp18 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP18' LIMIT 1);
SET @i_mp19 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP19' LIMIT 1);
SET @i_mp20 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP20' LIMIT 1);
SET @i_mp21 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP21' LIMIT 1);
SET @i_mp22 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP22' LIMIT 1);
SET @i_mp23 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP23' LIMIT 1);
SET @i_mp24 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP24' LIMIT 1);
SET @i_mp25 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP25' LIMIT 1);
SET @i_mp26 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP26' LIMIT 1);
SET @i_mp27 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP27' LIMIT 1);
SET @i_mp28 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP28' LIMIT 1);
SET @i_mp29 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP29' LIMIT 1);
SET @i_mp30 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP30' LIMIT 1);
SET @i_mp31 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP31' LIMIT 1);
SET @i_mp32 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP32' LIMIT 1);
SET @i_mp33 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP33' LIMIT 1);
SET @i_mp34 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP34' LIMIT 1);
SET @i_mp35 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP35' LIMIT 1);

-- Limpiar tabla y resetear AUTO_INCREMENT
DELETE FROM rest_receta_ingredientes;
ALTER TABLE rest_receta_ingredientes AUTO_INCREMENT = 1;

-- Insertar vínculos receta -> ingrediente (G* y MP* únicamente)
INSERT INTO rest_receta_ingredientes
  (receta_id, ingrediente_id, cantidad, unidad, notas, es_informativo, precio_extra, tipo_componente, codigo_display)
VALUES
  (3, @i_g1, 1.000, 'porción', NULL, 1, 15.00, 'materia_prima', NULL),
  (9, @i_g1, 1.000, 'porción', NULL, 1, 15.00, 'materia_prima', NULL),
  (10, @i_g1, 1.000, 'porción', NULL, 1, 15.00, 'materia_prima', NULL),
  (11, @i_g1, 1.000, 'porción', NULL, 1, 15.00, 'materia_prima', NULL),
  (12, @i_g1, 1.000, 'porción', NULL, 1, 15.00, 'materia_prima', NULL),
  (13, @i_g1, 1.000, 'porción', NULL, 1, 15.00, 'materia_prima', NULL),
  (14, @i_g1, 1.000, 'porción', NULL, 1, 15.00, 'materia_prima', NULL),
  (15, @i_g1, 1.000, 'porción', NULL, 1, 15.00, 'materia_prima', NULL),
  (16, @i_g1, 1.000, 'porción', NULL, 1, 15.00, 'materia_prima', NULL),
  (17, @i_g1, 1.000, 'porción', NULL, 1, 15.00, 'materia_prima', NULL),
  (18, @i_g1, 1.000, 'porción', NULL, 1, 15.00, 'materia_prima', NULL),
  (23, @i_g1, 1.000, 'porción', NULL, 1, 15.00, 'materia_prima', NULL),
  (28, @i_g1, 1.000, 'porción', NULL, 1, 15.00, 'materia_prima', NULL),
  (29, @i_g1, 1.000, 'porción', NULL, 1, 15.00, 'materia_prima', NULL),
  (30, @i_g1, 1.000, 'porción', NULL, 1, 15.00, 'materia_prima', NULL),
  (31, @i_g1, 1.000, 'porción', NULL, 1, 15.00, 'materia_prima', NULL),
  (34, @i_g1, 1.000, 'porción', NULL, 1, 15.00, 'materia_prima', NULL),
  (35, @i_g1, 1.000, 'porción', NULL, 1, 15.00, 'materia_prima', NULL),
  (1, @i_g2, 1.000, 'porción', NULL, 1, 10.00, 'materia_prima', NULL),
  (6, @i_g2, 1.000, 'porción', NULL, 1, 10.00, 'materia_prima', NULL),
  (7, @i_g2, 1.000, 'porción', NULL, 1, 10.00, 'materia_prima', NULL),
  (33, @i_g2, 1.000, 'porción', NULL, 1, 10.00, 'materia_prima', NULL),
  (3, @i_g3, 1.000, 'porción', NULL, 1, 10.00, 'materia_prima', NULL),
  (4, @i_g3, 1.000, 'porción', NULL, 1, 10.00, 'materia_prima', NULL),
  (5, @i_g3, 1.000, 'porción', NULL, 1, 10.00, 'materia_prima', NULL),
  (19, @i_g3, 1.000, 'porción', NULL, 1, 10.00, 'materia_prima', NULL),
  (20, @i_g3, 1.000, 'porción', NULL, 1, 10.00, 'materia_prima', NULL),
  (21, @i_g3, 1.000, 'porción', NULL, 1, 10.00, 'materia_prima', NULL),
  (22, @i_g3, 1.000, 'porción', NULL, 1, 10.00, 'materia_prima', NULL),
  (24, @i_g3, 1.000, 'porción', NULL, 1, 10.00, 'materia_prima', NULL),
  (25, @i_g3, 1.000, 'porción', NULL, 1, 10.00, 'materia_prima', NULL),
  (26, @i_g3, 1.000, 'porción', NULL, 1, 10.00, 'materia_prima', NULL),
  (27, @i_g3, 1.000, 'porción', NULL, 1, 10.00, 'materia_prima', NULL),
  (30, @i_g3, 1.000, 'porción', NULL, 1, 10.00, 'materia_prima', NULL),
  (1, @i_g4, 1.000, 'porción', NULL, 1, 15.00, 'materia_prima', NULL),
  (4, @i_g4, 1.000, 'porción', NULL, 1, 15.00, 'materia_prima', NULL),
  (5, @i_g4, 1.000, 'porción', NULL, 1, 15.00, 'materia_prima', NULL),
  (6, @i_g4, 1.000, 'porción', NULL, 1, 15.00, 'materia_prima', NULL),
  (7, @i_g4, 1.000, 'porción', NULL, 1, 15.00, 'materia_prima', NULL),
  (19, @i_g4, 1.000, 'porción', NULL, 1, 15.00, 'materia_prima', NULL),
  (20, @i_g4, 1.000, 'porción', NULL, 1, 15.00, 'materia_prima', NULL),
  (21, @i_g4, 1.000, 'porción', NULL, 1, 15.00, 'materia_prima', NULL),
  (22, @i_g4, 1.000, 'porción', NULL, 1, 15.00, 'materia_prima', NULL),
  (32, @i_g4, 1.000, 'porción', NULL, 1, 15.00, 'materia_prima', NULL),
  (33, @i_g4, 1.000, 'porción', NULL, 1, 15.00, 'materia_prima', NULL),
  (1, @i_g5, 1.000, 'porción', NULL, 1, 20.00, 'materia_prima', NULL),
  (4, @i_g5, 1.000, 'porción', NULL, 1, 20.00, 'materia_prima', NULL),
  (5, @i_g5, 1.000, 'porción', NULL, 1, 20.00, 'materia_prima', NULL),
  (6, @i_g5, 1.000, 'porción', NULL, 1, 20.00, 'materia_prima', NULL),
  (7, @i_g5, 1.000, 'porción', NULL, 1, 20.00, 'materia_prima', NULL),
  (19, @i_g5, 1.000, 'porción', NULL, 1, 20.00, 'materia_prima', NULL),
  (20, @i_g5, 1.000, 'porción', NULL, 1, 20.00, 'materia_prima', NULL),
  (21, @i_g5, 1.000, 'porción', NULL, 1, 20.00, 'materia_prima', NULL),
  (22, @i_g5, 1.000, 'porción', NULL, 1, 20.00, 'materia_prima', NULL),
  (32, @i_g5, 1.000, 'porción', NULL, 1, 20.00, 'materia_prima', NULL),
  (24, @i_g6, 1.000, 'porción', NULL, 1, 10.00, 'materia_prima', NULL),
  (25, @i_g6, 1.000, 'porción', NULL, 1, 10.00, 'materia_prima', NULL),
  (26, @i_g6, 1.000, 'porción', NULL, 1, 10.00, 'materia_prima', NULL),
  (27, @i_g6, 1.000, 'porción', NULL, 1, 10.00, 'materia_prima', NULL),
  (34, @i_g6, 1.000, 'porción', NULL, 1, 10.00, 'materia_prima', NULL),
  (35, @i_g6, 1.000, 'porción', NULL, 1, 10.00, 'materia_prima', NULL),
  (19, @i_g7, 1.000, 'porción', NULL, 1, 25.00, 'materia_prima', NULL),
  (20, @i_g7, 1.000, 'porción', NULL, 1, 25.00, 'materia_prima', NULL),
  (21, @i_g7, 1.000, 'porción', NULL, 1, 25.00, 'materia_prima', NULL),
  (22, @i_g7, 1.000, 'porción', NULL, 1, 25.00, 'materia_prima', NULL),
  (23, @i_g7, 1.000, 'porción', NULL, 1, 25.00, 'materia_prima', NULL),
  (28, @i_g7, 1.000, 'porción', NULL, 1, 25.00, 'materia_prima', NULL),
  (29, @i_g7, 1.000, 'porción', NULL, 1, 25.00, 'materia_prima', NULL),
  (30, @i_g7, 1.000, 'porción', NULL, 1, 25.00, 'materia_prima', NULL),
  (31, @i_g7, 1.000, 'porción', NULL, 1, 25.00, 'materia_prima', NULL),
  (3, @i_g8, 1.000, 'porción', NULL, 1, 5.00, 'materia_prima', NULL),
  (3, @i_g9, 1.000, 'porción', NULL, 1, 35.00, 'materia_prima', NULL),
  (23, @i_g9, 1.000, 'porción', NULL, 1, 35.00, 'materia_prima', NULL),
  (32, @i_g9, 1.000, 'porción', NULL, 1, 35.00, 'materia_prima', NULL),
  (33, @i_g9, 1.000, 'porción', NULL, 1, 35.00, 'materia_prima', NULL),
  (3, @i_g10, 1.000, 'porción', NULL, 1, 20.00, 'materia_prima', NULL),
  (19, @i_g12, 1.000, 'porción', NULL, 1, 35.00, 'materia_prima', NULL),
  (11, @i_g13, 1.000, 'porción', NULL, 1, 25.00, 'materia_prima', NULL),
  (12, @i_g13, 1.000, 'porción', NULL, 1, 25.00, 'materia_prima', NULL),
  (14, @i_g13, 1.000, 'porción', NULL, 1, 25.00, 'materia_prima', NULL),
  (16, @i_g13, 1.000, 'porción', NULL, 1, 25.00, 'materia_prima', NULL),
  (18, @i_g13, 1.000, 'porción', NULL, 1, 25.00, 'materia_prima', NULL),
  (9, @i_g14, 1.000, 'porción', NULL, 1, 25.00, 'materia_prima', NULL),
  (10, @i_g14, 1.000, 'porción', NULL, 1, 25.00, 'materia_prima', NULL),
  (13, @i_g14, 1.000, 'porción', NULL, 1, 25.00, 'materia_prima', NULL),
  (15, @i_g14, 1.000, 'porción', NULL, 1, 25.00, 'materia_prima', NULL),
  (17, @i_g14, 1.000, 'porción', NULL, 1, 25.00, 'materia_prima', NULL),
  (28, @i_g14, 1.000, 'porción', NULL, 1, 25.00, 'materia_prima', NULL),
  (29, @i_g14, 1.000, 'porción', NULL, 1, 25.00, 'materia_prima', NULL),
  (3, @i_g16, 1.000, 'porción', NULL, 1, 5.00, 'materia_prima', NULL),
  (34, @i_g16, 1.000, 'porción', NULL, 1, 5.00, 'materia_prima', NULL),
  (35, @i_g16, 1.000, 'porción', NULL, 1, 5.00, 'materia_prima', NULL),
  (3, @i_g17, 1.000, 'porción', NULL, 1, 10.00, 'materia_prima', NULL),
  (19, @i_g17, 1.000, 'porción', NULL, 1, 10.00, 'materia_prima', NULL),
  (20, @i_g17, 1.000, 'porción', NULL, 1, 10.00, 'materia_prima', NULL),
  (21, @i_g17, 1.000, 'porción', NULL, 1, 10.00, 'materia_prima', NULL),
  (22, @i_g17, 1.000, 'porción', NULL, 1, 10.00, 'materia_prima', NULL),
  (34, @i_g17, 1.000, 'porción', NULL, 1, 10.00, 'materia_prima', NULL),
  (35, @i_g17, 1.000, 'porción', NULL, 1, 10.00, 'materia_prima', NULL),
  (20, @i_g19, 1.000, 'porción', NULL, 1, 40.00, 'materia_prima', NULL),
  (21, @i_g19, 1.000, 'porción', NULL, 1, 40.00, 'materia_prima', NULL),
  (22, @i_g20, 1.000, 'porción', NULL, 1, 40.00, 'materia_prima', NULL),
  (31, @i_g21, 1.000, 'porción', NULL, 1, 10.00, 'materia_prima', NULL),
  (2, @i_g1, 1.000, 'g', NULL, 1, 0.00, 'guarnicion', 'G1'),
  (2, @i_g3, 1.000, 'g', NULL, 1, 0.00, 'guarnicion', 'G3'),
  (2, @i_g8, 1.000, 'g', NULL, 1, 0.00, 'guarnicion', 'G8'),
  (2, @i_g9, 1.000, 'g', NULL, 1, 0.00, 'guarnicion', 'G9'),
  (2, @i_g10, 1.000, 'g', NULL, 1, 0.00, 'guarnicion', 'G10'),
  (2, @i_g16, 1.000, 'g', NULL, 1, 0.00, 'guarnicion', 'G16'),
  (2, @i_g17, 1.000, 'g', NULL, 1, 0.00, 'guarnicion', 'G17'),
  (1, @i_mp1, 1.000, 'porcion', NULL, 0, 0.00, 'materia_prima', NULL),
  (2, @i_mp2, 1.000, 'porcion', NULL, 0, 0.00, 'materia_prima', NULL),
  (3, @i_mp3, 1.000, 'porcion', NULL, 0, 0.00, 'materia_prima', NULL),
  (4, @i_mp4, 1.000, 'porcion', NULL, 0, 0.00, 'materia_prima', NULL),
  (5, @i_mp5, 1.000, 'porcion', NULL, 0, 0.00, 'materia_prima', NULL),
  (6, @i_mp6, 1.000, 'porcion', NULL, 0, 0.00, 'materia_prima', NULL),
  (7, @i_mp7, 1.000, 'porcion', NULL, 0, 0.00, 'materia_prima', NULL),
  (8, @i_mp8, 1.000, 'porcion', NULL, 0, 0.00, 'materia_prima', NULL),
  (9, @i_mp9, 1.000, 'porcion', NULL, 0, 0.00, 'materia_prima', NULL),
  (10, @i_mp10, 1.000, 'porcion', NULL, 0, 0.00, 'materia_prima', NULL),
  (11, @i_mp11, 1.000, 'porcion', NULL, 0, 0.00, 'materia_prima', NULL),
  (12, @i_mp12, 1.000, 'porcion', NULL, 0, 0.00, 'materia_prima', NULL),
  (13, @i_mp13, 1.000, 'porcion', NULL, 0, 0.00, 'materia_prima', NULL),
  (14, @i_mp14, 1.000, 'porcion', NULL, 0, 0.00, 'materia_prima', NULL),
  (15, @i_mp15, 1.000, 'porcion', NULL, 0, 0.00, 'materia_prima', NULL),
  (16, @i_mp16, 1.000, 'porcion', NULL, 0, 0.00, 'materia_prima', NULL),
  (17, @i_mp17, 1.000, 'porcion', NULL, 0, 0.00, 'materia_prima', NULL),
  (18, @i_mp18, 1.000, 'porcion', NULL, 0, 0.00, 'materia_prima', NULL),
  (19, @i_mp19, 1.000, 'porcion', NULL, 0, 0.00, 'materia_prima', NULL),
  (20, @i_mp20, 1.000, 'porcion', NULL, 0, 0.00, 'materia_prima', NULL),
  (21, @i_mp21, 1.000, 'porcion', NULL, 0, 0.00, 'materia_prima', NULL),
  (22, @i_mp22, 1.000, 'porcion', NULL, 0, 0.00, 'materia_prima', NULL),
  (23, @i_mp23, 1.000, 'porcion', NULL, 0, 0.00, 'materia_prima', NULL),
  (24, @i_mp24, 1.000, 'porcion', NULL, 0, 0.00, 'materia_prima', NULL),
  (25, @i_mp25, 1.000, 'porcion', NULL, 0, 0.00, 'materia_prima', NULL),
  (26, @i_mp26, 1.000, 'porcion', NULL, 0, 0.00, 'materia_prima', NULL),
  (27, @i_mp27, 1.000, 'porcion', NULL, 0, 0.00, 'materia_prima', NULL),
  (28, @i_mp28, 1.000, 'porcion', NULL, 0, 0.00, 'materia_prima', NULL),
  (29, @i_mp29, 1.000, 'porcion', NULL, 0, 0.00, 'materia_prima', NULL),
  (30, @i_mp30, 1.000, 'porcion', NULL, 0, 0.00, 'materia_prima', NULL),
  (31, @i_mp31, 1.000, 'porcion', NULL, 0, 0.00, 'materia_prima', NULL),
  (32, @i_mp32, 1.000, 'porcion', NULL, 0, 0.00, 'materia_prima', NULL),
  (33, @i_mp33, 1.000, 'porcion', NULL, 0, 0.00, 'materia_prima', NULL),
  (34, @i_mp34, 1.000, 'porcion', NULL, 0, 0.00, 'materia_prima', NULL),
  (35, @i_mp35, 1.000, 'porcion', NULL, 0, 0.00, 'materia_prima', NULL),
  (1, @i_g2, 1.000, 'ml', NULL, 0, 0.00, 'materia_prima', NULL),
  (1, @i_g4, 1.000, 'ml', NULL, 0, 0.00, 'materia_prima', NULL),
  (1, @i_g5, 1.000, 'gr', NULL, 0, 0.00, 'materia_prima', NULL),
  (2, @i_g1, 1.000, 'porción', NULL, 0, 0.00, 'materia_prima', NULL),
  (2, @i_g3, 1.000, 'ml', NULL, 0, 0.00, 'materia_prima', NULL),
  (2, @i_g5, 1.000, 'gr', NULL, 0, 0.00, 'materia_prima', NULL),
  (2, @i_g8, 1.000, 'gr', NULL, 0, 0.00, 'materia_prima', NULL),
  (2, @i_g9, 1.000, 'gr', NULL, 0, 0.00, 'materia_prima', NULL),
  (2, @i_g10, 1.000, 'gr', NULL, 0, 0.00, 'materia_prima', NULL),
  (2, @i_g16, 1.000, 'gr', NULL, 0, 0.00, 'materia_prima', NULL),
  (2, @i_g17, 1.000, 'gr', NULL, 0, 0.00, 'materia_prima', NULL),
  (3, @i_g1, 1.000, 'porción', NULL, 0, 0.00, 'materia_prima', NULL),
  (3, @i_g3, 1.000, 'ml', NULL, 0, 0.00, 'materia_prima', NULL),
  (3, @i_g8, 1.000, 'gr', NULL, 0, 0.00, 'materia_prima', NULL),
  (3, @i_g9, 1.000, 'gr', NULL, 0, 0.00, 'materia_prima', NULL),
  (3, @i_g10, 1.000, 'gr', NULL, 0, 0.00, 'materia_prima', NULL),
  (3, @i_g16, 1.000, 'gr', NULL, 0, 0.00, 'materia_prima', NULL),
  (3, @i_g17, 1.000, 'gr', NULL, 0, 0.00, 'materia_prima', NULL),
  (4, @i_g3, 1.000, 'ml', NULL, 0, 0.00, 'materia_prima', NULL),
  (4, @i_g4, 1.000, 'ml', NULL, 0, 0.00, 'materia_prima', NULL),
  (4, @i_g5, 1.000, 'gr', NULL, 0, 0.00, 'materia_prima', NULL),
  (5, @i_g3, 1.000, 'ml', NULL, 0, 0.00, 'materia_prima', NULL),
  (5, @i_g4, 1.000, 'ml', NULL, 0, 0.00, 'materia_prima', NULL),
  (5, @i_g5, 1.000, 'gr', NULL, 0, 0.00, 'materia_prima', NULL),
  (6, @i_g2, 1.000, 'ml', NULL, 0, 0.00, 'materia_prima', NULL),
  (6, @i_g4, 1.000, 'ml', NULL, 0, 0.00, 'materia_prima', NULL),
  (6, @i_g5, 1.000, 'gr', NULL, 0, 0.00, 'materia_prima', NULL),
  (7, @i_g2, 1.000, 'ml', NULL, 0, 0.00, 'materia_prima', NULL),
  (7, @i_g4, 1.000, 'ml', NULL, 0, 0.00, 'materia_prima', NULL),
  (7, @i_g5, 1.000, 'gr', NULL, 0, 0.00, 'materia_prima', NULL),
  (9, @i_g1, 1.000, 'porción', NULL, 0, 0.00, 'materia_prima', NULL),
  (9, @i_g14, 1.000, 'paquete', NULL, 0, 0.00, 'materia_prima', NULL),
  (10, @i_g1, 1.000, 'porción', NULL, 0, 0.00, 'materia_prima', NULL),
  (10, @i_g14, 1.000, 'paquete', NULL, 0, 0.00, 'materia_prima', NULL),
  (11, @i_g1, 1.000, 'porción', NULL, 0, 0.00, 'materia_prima', NULL),
  (11, @i_g13, 1.000, 'gr', NULL, 0, 0.00, 'materia_prima', NULL),
  (12, @i_g1, 1.000, 'porción', NULL, 0, 0.00, 'materia_prima', NULL),
  (12, @i_g13, 1.000, 'gr', NULL, 0, 0.00, 'materia_prima', NULL),
  (13, @i_g1, 1.000, 'porción', NULL, 0, 0.00, 'materia_prima', NULL),
  (13, @i_g14, 1.000, 'paquete', NULL, 0, 0.00, 'materia_prima', NULL),
  (14, @i_g1, 1.000, 'porción', NULL, 0, 0.00, 'materia_prima', NULL),
  (14, @i_g13, 1.000, 'gr', NULL, 0, 0.00, 'materia_prima', NULL),
  (15, @i_g1, 1.000, 'porción', NULL, 0, 0.00, 'materia_prima', NULL),
  (15, @i_g14, 1.000, 'paquete', NULL, 0, 0.00, 'materia_prima', NULL),
  (16, @i_g1, 1.000, 'porción', NULL, 0, 0.00, 'materia_prima', NULL),
  (16, @i_g13, 1.000, 'gr', NULL, 0, 0.00, 'materia_prima', NULL),
  (17, @i_g1, 1.000, 'porción', NULL, 0, 0.00, 'materia_prima', NULL),
  (17, @i_g14, 1.000, 'paquete', NULL, 0, 0.00, 'materia_prima', NULL),
  (18, @i_g1, 1.000, 'porción', NULL, 0, 0.00, 'materia_prima', NULL),
  (18, @i_g13, 1.000, 'gr', NULL, 0, 0.00, 'materia_prima', NULL),
  (19, @i_g3, 1.000, 'ml', NULL, 0, 0.00, 'materia_prima', NULL),
  (19, @i_g4, 1.000, 'ml', NULL, 0, 0.00, 'materia_prima', NULL),
  (19, @i_g5, 1.000, 'gr', NULL, 0, 0.00, 'materia_prima', NULL),
  (19, @i_g7, 1.000, 'paquete', NULL, 0, 0.00, 'materia_prima', NULL),
  (19, @i_g12, 1.000, 'gr', NULL, 0, 0.00, 'materia_prima', NULL),
  (19, @i_g17, 1.000, 'gr', NULL, 0, 0.00, 'materia_prima', NULL),
  (20, @i_g3, 1.000, 'ml', NULL, 0, 0.00, 'materia_prima', NULL),
  (20, @i_g4, 1.000, 'ml', NULL, 0, 0.00, 'materia_prima', NULL),
  (20, @i_g5, 1.000, 'gr', NULL, 0, 0.00, 'materia_prima', NULL),
  (20, @i_g7, 1.000, 'paquete', NULL, 0, 0.00, 'materia_prima', NULL),
  (20, @i_g17, 1.000, 'gr', NULL, 0, 0.00, 'materia_prima', NULL),
  (20, @i_g19, 1.000, 'gr', NULL, 0, 0.00, 'materia_prima', NULL),
  (21, @i_g3, 1.000, 'ml', NULL, 0, 0.00, 'materia_prima', NULL),
  (21, @i_g4, 1.000, 'ml', NULL, 0, 0.00, 'materia_prima', NULL),
  (21, @i_g5, 1.000, 'gr', NULL, 0, 0.00, 'materia_prima', NULL),
  (21, @i_g7, 1.000, 'paquete', NULL, 0, 0.00, 'materia_prima', NULL),
  (21, @i_g17, 1.000, 'gr', NULL, 0, 0.00, 'materia_prima', NULL),
  (21, @i_g19, 1.000, 'gr', NULL, 0, 0.00, 'materia_prima', NULL),
  (22, @i_g3, 1.000, 'ml', NULL, 0, 0.00, 'materia_prima', NULL),
  (22, @i_g4, 1.000, 'ml', NULL, 0, 0.00, 'materia_prima', NULL),
  (22, @i_g5, 1.000, 'gr', NULL, 0, 0.00, 'materia_prima', NULL),
  (22, @i_g7, 1.000, 'paquete', NULL, 0, 0.00, 'materia_prima', NULL),
  (22, @i_g17, 1.000, 'gr', NULL, 0, 0.00, 'materia_prima', NULL),
  (22, @i_g20, 1.000, 'gr', NULL, 0, 0.00, 'materia_prima', NULL),
  (23, @i_g1, 1.000, 'porción', NULL, 0, 0.00, 'materia_prima', NULL),
  (23, @i_g7, 1.000, 'paquete', NULL, 0, 0.00, 'materia_prima', NULL),
  (23, @i_g9, 1.000, 'gr', NULL, 0, 0.00, 'materia_prima', NULL),
  (24, @i_g3, 1.000, 'ml', NULL, 0, 0.00, 'materia_prima', NULL),
  (24, @i_g6, 1.000, 'pza', NULL, 0, 0.00, 'materia_prima', NULL),
  (25, @i_g3, 1.000, 'ml', NULL, 0, 0.00, 'materia_prima', NULL),
  (25, @i_g6, 1.000, 'pza', NULL, 0, 0.00, 'materia_prima', NULL),
  (26, @i_g3, 1.000, 'ml', NULL, 0, 0.00, 'materia_prima', NULL),
  (26, @i_g6, 1.000, 'pza', NULL, 0, 0.00, 'materia_prima', NULL),
  (27, @i_g3, 1.000, 'ml', NULL, 0, 0.00, 'materia_prima', NULL),
  (27, @i_g6, 1.000, 'pza', NULL, 0, 0.00, 'materia_prima', NULL),
  (28, @i_g1, 1.000, 'porción', NULL, 0, 0.00, 'materia_prima', NULL),
  (28, @i_g7, 1.000, 'paquete', NULL, 0, 0.00, 'materia_prima', NULL),
  (28, @i_g14, 1.000, 'paquete', NULL, 0, 0.00, 'materia_prima', NULL),
  (29, @i_g1, 1.000, 'porción', NULL, 0, 0.00, 'materia_prima', NULL),
  (29, @i_g7, 1.000, 'paquete', NULL, 0, 0.00, 'materia_prima', NULL),
  (29, @i_g14, 1.000, 'paquete', NULL, 0, 0.00, 'materia_prima', NULL),
  (30, @i_g1, 1.000, 'porción', NULL, 0, 0.00, 'materia_prima', NULL),
  (30, @i_g3, 1.000, 'ml', NULL, 0, 0.00, 'materia_prima', NULL),
  (30, @i_g7, 1.000, 'paquete', NULL, 0, 0.00, 'materia_prima', NULL),
  (31, @i_g1, 1.000, 'porción', NULL, 0, 0.00, 'materia_prima', NULL),
  (31, @i_g7, 1.000, 'paquete', NULL, 0, 0.00, 'materia_prima', NULL),
  (31, @i_g21, 1.000, 'gr', NULL, 0, 0.00, 'materia_prima', NULL),
  (32, @i_g3, 1.000, 'ml', NULL, 0, 0.00, 'materia_prima', NULL),
  (32, @i_g4, 1.000, 'ml', NULL, 0, 0.00, 'materia_prima', NULL),
  (32, @i_g5, 1.000, 'gr', NULL, 0, 0.00, 'materia_prima', NULL),
  (32, @i_g9, 1.000, 'gr', NULL, 0, 0.00, 'materia_prima', NULL),
  (33, @i_g2, 1.000, 'ml', NULL, 0, 0.00, 'materia_prima', NULL),
  (33, @i_g4, 1.000, 'ml', NULL, 0, 0.00, 'materia_prima', NULL),
  (33, @i_g5, 1.000, 'gr', NULL, 0, 0.00, 'materia_prima', NULL),
  (33, @i_g9, 1.000, 'gr', NULL, 0, 0.00, 'materia_prima', NULL),
  (34, @i_g1, 1.000, 'porción', NULL, 0, 0.00, 'materia_prima', NULL),
  (34, @i_g6, 1.000, 'pza', NULL, 0, 0.00, 'materia_prima', NULL),
  (34, @i_g16, 1.000, 'gr', NULL, 0, 0.00, 'materia_prima', NULL),
  (34, @i_g17, 1.000, 'gr', NULL, 0, 0.00, 'materia_prima', NULL),
  (35, @i_g1, 1.000, 'porción', NULL, 0, 0.00, 'materia_prima', NULL),
  (35, @i_g6, 1.000, 'pza', NULL, 0, 0.00, 'materia_prima', NULL),
  (35, @i_g16, 1.000, 'gr', NULL, 0, 0.00, 'materia_prima', NULL),
  (35, @i_g17, 1.000, 'gr', NULL, 0, 0.00, 'materia_prima', NULL);

COMMIT;

-- Verificación post-ejecución:
-- SELECT COUNT(*) AS total, SUM(es_informativo) AS informativos FROM rest_receta_ingredientes;
-- Esperado: total = 258