import React, { useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
} from 'react-native';
import { Image } from 'expo-image';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';

import { formatImageUrl } from '../../services/api';
import { useDish } from '../../hooks/useMenu';
import { useCartStore } from '../../store/cart.store';
import { useTableSessionStore } from '../../store/table-session.store';
import { useFavorites } from '../../hooks/useFavorites';
import { Button } from '../../components/ui/Button';
import { Skeleton } from '../../components/ui/Skeleton';
import { CartButton } from '../../components/shared/CartButton';
import { Colors, Spacing, Typography, Shadows } from '../../theme';
import type {
  ModificadorSeleccionado,
  OpcionModificador,
} from '@amare/types';

export default function ProductScreen() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { id, restauranteId } = useLocalSearchParams<{
    id: string;
    restauranteId: string;
  }>();

  const { data: platillo, isLoading, isError } = useDish(
    Number(restauranteId),
    Number(id)
  );

  const addItem = useCartStore((s) => s.addItem);
  const tipoPedido = useCartStore((s) => s.tipoPedido);
  const tableSession = useTableSessionStore((s) => s.session);
  const { data: favorites, toggle } = useFavorites();

  const isFav = favorites?.some((f: any) => f.id === platillo?.id);
  const [cantidad, setCantidad] = useState(1);
  const [modsSel, setModsSel] = useState<ModificadorSeleccionado[]>([]);

  function goBack() {
    router.back();
  }

  function toggleOpcion(
    modId: number,
    modNombre: string,
    opcion: OpcionModificador,
    tipo: 'radio' | 'checkbox'
  ) {
    const nuevoItem: ModificadorSeleccionado = {
      modificador_id: modId,
      modificador_nombre: modNombre,
      opciones: [
        {
          opcion_id: opcion.id,
          opcion_nombre: opcion.nombre,
          precio_extra: opcion.precio_extra,
        },
      ],
    };

    setModsSel((prev) => {
      if (tipo === 'radio') {
        return [
          ...prev.filter((m) => m.modificador_id !== modId),
          nuevoItem,
        ];
      }

      const existing = prev.find((m) => m.modificador_id === modId);

      if (existing) {
        const yaSelec = existing.opciones.some(
          (o) => o.opcion_id === opcion.id
        );

        const nuevasOpciones = yaSelec
          ? existing.opciones.filter((o) => o.opcion_id !== opcion.id)
          : [
              ...existing.opciones,
              {
                opcion_id: opcion.id,
                opcion_nombre: opcion.nombre,
                precio_extra: opcion.precio_extra,
              },
            ];

        if (nuevasOpciones.length === 0) {
          return prev.filter((m) => m.modificador_id !== modId);
        }

        return [
          ...prev.filter((m) => m.modificador_id !== modId),
          { ...existing, opciones: nuevasOpciones },
        ];
      }

      return [...prev, nuevoItem];
    });
  }

  function handleAddToCart() {
    if (!platillo) return;

    if (tipoPedido === 'eat_in' && !tableSession) {
      router.replace({
        pathname: '/table-scanner',
        params: { returnTo: '/(tabs)' },
      });
      return;
    }

    if (
      tipoPedido === 'eat_in' &&
      tableSession &&
      platillo.restaurante_id !== tableSession.restauranteId
    ) {
      router.replace({
        pathname: '/table-scanner',
        params: { returnTo: '/(tabs)' },
      });
      return;
    }

    addItem(platillo, cantidad, modsSel, '');
  }

  if (isError) {
    return (
      <SafeAreaView style={styles.screen}>
        <View style={styles.errorSheet}>
          <Ionicons
            name="restaurant-outline"
            size={44}
            color={Colors.textMuted}
          />
          <Text style={styles.notFoundTitle}>Platillo no disponible</Text>
          <Text style={styles.notFoundText}>
            Este platillo no pertenece a la sucursal seleccionada.
          </Text>
          <Button label="Volver al menu" onPress={goBack} fullWidth />
        </View>
      </SafeAreaView>
    );
  }

  if (isLoading || !platillo) {
    return (
      <SafeAreaView style={styles.screen}>
        <Skeleton height={260} borderRadius={0} />
        <View style={styles.loadingContent}>
          <Skeleton height={30} width="60%" />
          <Skeleton height={42} width={140} borderRadius={999} />
          <Skeleton height={18} />
          <Skeleton height={18} width="84%" />
        </View>
      </SafeAreaView>
    );
  }

  const total = platillo.precio * cantidad;
  const footerPadding = Math.max(insets.bottom, 18);

  return (
    <SafeAreaView style={styles.screen}>
      <View style={styles.body}>
        <ScrollView
          style={styles.scroll}
          contentContainerStyle={styles.scrollContent}
          showsVerticalScrollIndicator={false}
          bounces={false}
        >
            <View style={styles.imageContainer}>
              <Image
                source={
                  formatImageUrl(platillo.imagen) ??
                  require('../../assets/placeholder-food.jpg')
                }
                style={styles.image}
                contentFit="cover"
              />

              <TouchableOpacity
                style={[styles.iconBtn, styles.favoriteBtn]}
                onPress={() => toggle(platillo.id)}
                activeOpacity={0.85}
              >
                <Ionicons
                  name={isFav ? 'heart' : 'heart-outline'}
                  size={21}
                  color={isFav ? Colors.error : Colors.text}
                />
              </TouchableOpacity>

              <TouchableOpacity style={[styles.iconBtn, styles.closeBtn]} onPress={goBack} activeOpacity={0.85}>
                <Ionicons name="arrow-back" size={22} color={Colors.text} />
              </TouchableOpacity>
            </View>

            <View style={styles.content}>
              <Text style={styles.nombre}>{platillo.nombre}</Text>

              <View style={styles.pricePill}>
                <Ionicons name="cash-outline" size={16} color={Colors.text} />
                <Text style={styles.heroPrice}>${platillo.precio.toFixed(2)}</Text>
              </View>

              {platillo.descripcion ? (
                <Text style={styles.heroDescription}>{platillo.descripcion}</Text>
              ) : null}

              {platillo.modificadores?.map((mod) => (
                <View key={mod.id} style={styles.modGroup}>
                  <Text style={styles.modTitle}>
                    {mod.nombre}
                    {mod.requerido ? <Text style={styles.req}> *</Text> : null}
                  </Text>

                  <Text style={styles.modSubtitle}>
                    {mod.tipo === 'radio'
                      ? 'Elige una opcion'
                      : 'Selecciona las que quieras'}
                  </Text>

                  {mod.opciones.map((opcion) => {
                    const selMod = modsSel.find(
                      (m) => m.modificador_id === mod.id
                    );

                    const isSelected =
                      selMod?.opciones.some(
                        (o) => o.opcion_id === opcion.id
                      ) ?? false;

                    return (
                      <TouchableOpacity
                        key={opcion.id}
                        style={styles.opcionRow}
                        onPress={() =>
                          toggleOpcion(
                            mod.id,
                            mod.nombre,
                            opcion,
                            mod.tipo as 'radio' | 'checkbox'
                          )
                        }
                        activeOpacity={0.8}
                      >
                        <View
                          style={[
                            styles.checkBox,
                            isSelected && styles.checkBoxActive,
                          ]}
                        >
                          {isSelected ? (
                            <Ionicons
                              name="checkmark"
                              size={12}
                              color={Colors.white}
                            />
                          ) : null}
                        </View>

                        <Text style={styles.opcionNombre}>{opcion.nombre}</Text>

                        {(opcion.precio_extra ?? 0) > 0 ? (
                          <Text style={styles.opcionPrecio}>
                            +${opcion.precio_extra!.toFixed(2)}
                          </Text>
                        ) : null}
                      </TouchableOpacity>
                    );
                  })}
                </View>
              ))}
            </View>
        </ScrollView>

        <View style={[styles.footer, { paddingBottom: footerPadding }]}>
            <View style={styles.qtyRow}>
              <TouchableOpacity
                onPress={() => setCantidad((c) => Math.max(1, c - 1))}
                style={styles.qtyBtn}
                activeOpacity={0.8}
              >
                <Ionicons name="remove" size={18} color={Colors.text} />
              </TouchableOpacity>

              <Text style={styles.qty}>{cantidad}</Text>

              <TouchableOpacity
                onPress={() => setCantidad((c) => c + 1)}
                style={styles.qtyBtn}
                activeOpacity={0.8}
              >
                <Ionicons name="add" size={18} color={Colors.text} />
              </TouchableOpacity>
            </View>

            <Button
              label={`Agregar · $${total.toFixed(2)}`}
              onPress={handleAddToCart}
              style={styles.addButton}
              size="lg"
              disabled={!platillo.disponible}
            />
        </View>
      </View>
      <CartButton />
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  screen: {
    flex: 1,
    backgroundColor: '#FFFDFC',
  },
  body: {
    flex: 1,
  },
  errorSheet: {
    margin: 16,
    padding: 24,
    gap: 12,
    backgroundColor: '#FFFDFC',
    borderRadius: 28,
    alignItems: 'center',
    ...Shadows.md,
  },
  scroll: {
    flex: 1,
  },
  scrollContent: {
    paddingBottom: 12,
  },
  loadingContent: {
    paddingHorizontal: Spacing.base,
    paddingTop: Spacing.lg,
    paddingBottom: Spacing.xl,
    gap: 12,
  },
  imageContainer: {
    width: '100%',
    height: 320,
    position: 'relative',
    backgroundColor: '#EFE8DE',
  },
  image: {
    width: '100%',
    height: '100%',
  },
  iconBtn: {
    position: 'absolute',
    top: 16,
    width: 42,
    height: 42,
    borderRadius: 21,
    backgroundColor: 'rgba(255,255,255,0.96)',
    alignItems: 'center',
    justifyContent: 'center',
    ...Shadows.sm,
  },
  favoriteBtn: {
    right: 16,
  },
  closeBtn: {
    left: 16,
  },
  content: {
    paddingHorizontal: Spacing.base,
    paddingTop: Spacing.lg,
    paddingBottom: Spacing.base,
    gap: 16,
  },
  nombre: {
    fontFamily: 'PlayfairDisplay_700Bold',
    fontSize: 28,
    color: Colors.text,
    lineHeight: 34,
  },
  pricePill: {
    flexDirection: 'row',
    alignItems: 'center',
    alignSelf: 'flex-start',
    gap: 8,
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderRadius: 999,
    backgroundColor: '#F4EFE7',
    borderWidth: 1,
    borderColor: '#E8DED1',
  },
  heroPrice: {
    ...Typography.body,
    color: Colors.text,
    fontWeight: '800',
  },
  heroDescription: {
    ...Typography.body,
    color: Colors.textMuted,
    fontSize: 17,
    lineHeight: 29,
  },
  modGroup: {
    backgroundColor: '#FAF8F5',
    borderWidth: 1,
    borderColor: '#ECE4D8',
    borderRadius: 18,
    padding: Spacing.md,
    gap: 8,
  },
  modTitle: {
    fontSize: 15,
    fontWeight: '700',
    color: Colors.text,
  },
  req: {
    color: Colors.error,
  },
  modSubtitle: {
    fontSize: 12,
    color: Colors.textMuted,
    marginBottom: 4,
  },
  opcionRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    paddingVertical: 6,
  },
  checkBox: {
    width: 22,
    height: 22,
    borderRadius: 6,
    borderWidth: 1.5,
    borderColor: Colors.border,
    alignItems: 'center',
    justifyContent: 'center',
  },
  checkBoxActive: {
    backgroundColor: Colors.primary,
    borderColor: Colors.primary,
  },
  opcionNombre: {
    flex: 1,
    fontSize: 14,
    color: Colors.text,
  },
  opcionPrecio: {
    fontSize: 13,
    color: Colors.textMuted,
    fontWeight: '600',
  },
  footer: {
    flexDirection: 'row',
    gap: Spacing.sm,
    paddingHorizontal: Spacing.base,
    paddingTop: Spacing.sm,
    backgroundColor: '#FFFDFC',
    borderTopWidth: 1,
    borderTopColor: '#EFE7DD',
  },
  qtyRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    minWidth: 112,
    paddingHorizontal: 12,
    borderRadius: 18,
    backgroundColor: '#F6F2EC',
    borderWidth: 1,
    borderColor: '#E8DED1',
  },
  qtyBtn: {
    paddingVertical: 16,
    paddingHorizontal: 4,
  },
  qty: {
    minWidth: 26,
    textAlign: 'center',
    fontSize: 18,
    fontWeight: '700',
    color: Colors.text,
  },
  addButton: {
    flex: 1,
    borderRadius: 18,
  },
  notFoundTitle: {
    ...Typography.h3,
    color: Colors.text,
    textAlign: 'center',
  },
  notFoundText: {
    ...Typography.body,
    color: Colors.textMuted,
    textAlign: 'center',
    marginBottom: Spacing.sm,
  },
});
