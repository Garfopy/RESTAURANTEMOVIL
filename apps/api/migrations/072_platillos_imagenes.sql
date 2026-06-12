-- 072_platillos_imagenes.sql
-- Actualiza la columna imagen de rest_platillos (restaurante_id = 1)
-- con las rutas provenientes del dump de phpMyAdmin.
-- La API sirve estas rutas desde apps/api/public/ via express.static('/public', ...).

UPDATE rest_platillos SET imagen = 'public/uploads/platillos/platillo_1_1_1779994826.jpg'   WHERE id = 1   AND restaurante_id = 1;
UPDATE rest_platillos SET imagen = 'public/uploads/platillos/platillo_1_2_1779994433.jpg'   WHERE id = 2   AND restaurante_id = 1;
UPDATE rest_platillos SET imagen = 'public/uploads/platillos/platillo_1_3_1779994282.jpg'   WHERE id = 3   AND restaurante_id = 1;
UPDATE rest_platillos SET imagen = 'public/uploads/platillos/platillo_1_4_1779996314.jpg'   WHERE id = 4   AND restaurante_id = 1;
UPDATE rest_platillos SET imagen = 'public/uploads/platillos/platillo_1_5_1779996298.jpg'   WHERE id = 5   AND restaurante_id = 1;
UPDATE rest_platillos SET imagen = 'public/uploads/platillos/platillo_1_6_1779996468.jpg'   WHERE id = 6   AND restaurante_id = 1;
UPDATE rest_platillos SET imagen = 'public/uploads/platillos/platillo_1_7_1779996447.jpg'   WHERE id = 7   AND restaurante_id = 1;
UPDATE rest_platillos SET imagen = 'public/uploads/platillos/platillo_1_8_1779996433.jpg'   WHERE id = 8   AND restaurante_id = 1;
UPDATE rest_platillos SET imagen = 'public/uploads/platillos/platillo_1_9_1779995721.jpg'   WHERE id = 9   AND restaurante_id = 1;
UPDATE rest_platillos SET imagen = 'public/uploads/platillos/platillo_1_10_1779995702.jpg'  WHERE id = 10  AND restaurante_id = 1;
UPDATE rest_platillos SET imagen = 'public/uploads/platillos/platillo_1_11_1779995829.jpg'  WHERE id = 11  AND restaurante_id = 1;
UPDATE rest_platillos SET imagen = 'public/uploads/platillos/platillo_1_12_1779995813.jpg'  WHERE id = 12  AND restaurante_id = 1;
UPDATE rest_platillos SET imagen = 'public/uploads/platillos/platillo_1_13_1779995481.jpeg' WHERE id = 13  AND restaurante_id = 1;
UPDATE rest_platillos SET imagen = 'public/uploads/platillos/platillo_1_14_1779995497.jpg'  WHERE id = 14  AND restaurante_id = 1;
UPDATE rest_platillos SET imagen = 'public/uploads/platillos/platillo_1_15_1779995387.jpg'  WHERE id = 15  AND restaurante_id = 1;
UPDATE rest_platillos SET imagen = 'public/uploads/platillos/platillo_1_16_1779995407.jpg'  WHERE id = 16  AND restaurante_id = 1;
UPDATE rest_platillos SET imagen = 'public/uploads/platillos/platillo_1_17_1779995248.jpg'  WHERE id = 17  AND restaurante_id = 1;
UPDATE rest_platillos SET imagen = 'public/uploads/platillos/platillo_1_18_1779995306.jpg'  WHERE id = 18  AND restaurante_id = 1;
UPDATE rest_platillos SET imagen = 'public/uploads/platillos/platillo_1_19_1779996097.jpg'  WHERE id = 19  AND restaurante_id = 1;
UPDATE rest_platillos SET imagen = 'public/uploads/platillos/platillo_1_20_1779996113.jpg'  WHERE id = 20  AND restaurante_id = 1;
UPDATE rest_platillos SET imagen = 'public/uploads/platillos/platillo_1_21_1779995069.jpg'  WHERE id = 21  AND restaurante_id = 1;
UPDATE rest_platillos SET imagen = 'public/uploads/platillos/platillo_1_22_1779995008.jpg'  WHERE id = 22  AND restaurante_id = 1;
UPDATE rest_platillos SET imagen = 'public/uploads/platillos/platillo_1_23_1779994698.jpg'  WHERE id = 23  AND restaurante_id = 1;
UPDATE rest_platillos SET imagen = 'public/uploads/platillos/platillo_1_24_1779996241.jpg'  WHERE id = 24  AND restaurante_id = 1;
UPDATE rest_platillos SET imagen = 'public/uploads/platillos/platillo_1_25_1779996222.jpg'  WHERE id = 25  AND restaurante_id = 1;
UPDATE rest_platillos SET imagen = 'public/uploads/platillos/platillo_1_26_1779996181.jpg'  WHERE id = 26  AND restaurante_id = 1;
UPDATE rest_platillos SET imagen = 'public/uploads/platillos/platillo_1_27_1779996204.jpg'  WHERE id = 27  AND restaurante_id = 1;
UPDATE rest_platillos SET imagen = 'public/uploads/platillos/platillo_1_28_1779994542.jpg'  WHERE id = 28  AND restaurante_id = 1;
UPDATE rest_platillos SET imagen = 'public/uploads/platillos/platillo_1_29_1779994591.jpg'  WHERE id = 29  AND restaurante_id = 1;
UPDATE rest_platillos SET imagen = 'public/uploads/platillos/platillo_1_30_1779994239.jpg'  WHERE id = 30  AND restaurante_id = 1;
UPDATE rest_platillos SET imagen = 'public/uploads/platillos/platillo_1_31_1779994753.jpg'  WHERE id = 31  AND restaurante_id = 1;
UPDATE rest_platillos SET imagen = 'public/uploads/platillos/platillo_1_32_1779994917.jpg'  WHERE id = 32  AND restaurante_id = 1;
UPDATE rest_platillos SET imagen = 'public/uploads/platillos/platillo_1_33_1779994881.jpg'  WHERE id = 33  AND restaurante_id = 1;
UPDATE rest_platillos SET imagen = 'public/uploads/platillos/platillo_1_34_1779995887.jpg'  WHERE id = 34  AND restaurante_id = 1;
UPDATE rest_platillos SET imagen = 'public/uploads/platillos/platillo_1_35_1779995915.png'  WHERE id = 35  AND restaurante_id = 1;
UPDATE rest_platillos SET imagen = 'public/uploads/platillos/platillo_1_71_1779996570.jpg'  WHERE id = 71  AND restaurante_id = 1;
UPDATE rest_platillos SET imagen = 'public/uploads/platillos/platillo_1_72_1779996666.jpg'  WHERE id = 72  AND restaurante_id = 1;
UPDATE rest_platillos SET imagen = 'public/uploads/platillos/platillo_1_73_1779997118.jpg'  WHERE id = 73  AND restaurante_id = 1;
UPDATE rest_platillos SET imagen = 'public/uploads/platillos/platillo_1_74_1779997322.png'  WHERE id = 74  AND restaurante_id = 1;
UPDATE rest_platillos SET imagen = 'public/uploads/platillos/platillo_1_75_1779996756.png'  WHERE id = 75  AND restaurante_id = 1;
UPDATE rest_platillos SET imagen = 'public/uploads/platillos/platillo_1_76_1779996710.jpeg' WHERE id = 76  AND restaurante_id = 1;
UPDATE rest_platillos SET imagen = 'public/uploads/platillos/platillo_1_77_1779997009.jpg'  WHERE id = 77  AND restaurante_id = 1;
UPDATE rest_platillos SET imagen = 'public/uploads/platillos/platillo_1_78_1779997066.jpg'  WHERE id = 78  AND restaurante_id = 1;
UPDATE rest_platillos SET imagen = 'public/uploads/platillos/platillo_1_79_1779996960.jpg'  WHERE id = 79  AND restaurante_id = 1;
UPDATE rest_platillos SET imagen = 'public/uploads/platillos/platillo_1_80_1779997512.jpg'  WHERE id = 80  AND restaurante_id = 1;
UPDATE rest_platillos SET imagen = 'public/uploads/platillos/platillo_1_81_1779996886.jpg'  WHERE id = 81  AND restaurante_id = 1;
UPDATE rest_platillos SET imagen = 'public/uploads/platillos/platillo_1_82_1779997550.jpg'  WHERE id = 82  AND restaurante_id = 1;
UPDATE rest_platillos SET imagen = 'public/uploads/platillos/platillo_1_83_1779997178.jpg'  WHERE id = 83  AND restaurante_id = 1;
UPDATE rest_platillos SET imagen = 'public/uploads/platillos/platillo_1_84_1779997265.jpg'  WHERE id = 84  AND restaurante_id = 1;
UPDATE rest_platillos SET imagen = 'public/uploads/platillos/platillo_1_85_1779997243.jpg'  WHERE id = 85  AND restaurante_id = 1;
UPDATE rest_platillos SET imagen = 'public/uploads/platillos/platillo_1_86_1779997421.jpg'  WHERE id = 86  AND restaurante_id = 1;
UPDATE rest_platillos SET imagen = 'public/uploads/platillos/platillo_1_87_1779997656.jpg'  WHERE id = 87  AND restaurante_id = 1;
UPDATE rest_platillos SET imagen = 'public/uploads/platillos/platillo_1_88_1779997626.jpg'  WHERE id = 88  AND restaurante_id = 1;
-- ids 89-105: imagen NULL en el dump, se dejan sin cambio
UPDATE rest_platillos SET imagen = 'public/uploads/platillos/platillo_1_106_1779998161.jpg' WHERE id = 106 AND restaurante_id = 1;
-- ids 107-108: imagen NULL en el dump
UPDATE rest_platillos SET imagen = 'public/uploads/platillos/platillo_1_109_1779997729.jpg' WHERE id = 109 AND restaurante_id = 1;
UPDATE rest_platillos SET imagen = 'public/uploads/platillos/platillo_1_110_1779997769.jpg' WHERE id = 110 AND restaurante_id = 1;
UPDATE rest_platillos SET imagen = 'public/uploads/platillos/platillo_1_111_1779997809.jpg' WHERE id = 111 AND restaurante_id = 1;
-- ids 112-113: imagen NULL en el dump
UPDATE rest_platillos SET imagen = 'public/uploads/platillos/platillo_1_114_1779997948.jpg' WHERE id = 114 AND restaurante_id = 1;
UPDATE rest_platillos SET imagen = 'public/uploads/platillos/platillo_1_115_1779998074.jpg' WHERE id = 115 AND restaurante_id = 1;
UPDATE rest_platillos SET imagen = 'public/uploads/platillos/platillo_1_116_1779997842.jpg' WHERE id = 116 AND restaurante_id = 1;
UPDATE rest_platillos SET imagen = 'public/uploads/platillos/platillo_1_117_1779997887.jpg' WHERE id = 117 AND restaurante_id = 1;
-- ids 118-123: imagen NULL en el dump
UPDATE rest_platillos SET imagen = 'public/uploads/platillos/platillo_1_new_1779724681.jpg' WHERE id = 125 AND restaurante_id = 1;
