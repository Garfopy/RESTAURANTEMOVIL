-- Seed de coordenadas conocidas para seleccion de sucursal cercana.
-- Amare usa la direccion "Catedral de Queretaro, Centro Historico".
UPDATE rest_restaurantes
SET lat = 20.591403,
    lng = -100.396631
WHERE id = 1
  AND (lat IS NULL OR lng IS NULL);

-- Amare Sur / Juan Maldonado 521 requiere coordenada verificada antes de produccion.
