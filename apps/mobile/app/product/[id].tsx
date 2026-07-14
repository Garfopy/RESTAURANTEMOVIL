import React, { useEffect, useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  RefreshControl,
} from 'react-native';
import { Image } from 'expo-image';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';

import { formatImageUrl } from '../../services/api';
import { useDish } from '../../hooks/useMenu';
import { useCartStore } from '../../store/cart.store';
import { useTableSessionStore } from '../../store/table-session.store';
import { useBranchConfigStore } from '../../store/branch.store';
import { useFavorites } from '../../hooks/useFavorites';
import { Button } from '../../components/ui/Button';
import { Skeleton } from '../../components/ui/Skeleton';
import { CartButton } from '../../components/shared/CartButton';
import { TableContextBanner } from '../../components/shared/TableContextBanner';
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
    restauranteId?: string;
  }>();
  const parsedRestaurantId = typeof restauranteId === 'string' && restauranteId !== ''
    ? Number(restauranteId)
    : undefined;
  const parsedProductId = Number(id);

  const { data: platillo, isLoading, isError, isRefetching, refetch } = useDish(
    parsedRestaurantId,
    parsedProductId
  );

  const addItem = useCartStore((s) => s.addItem);
  const tipoPedido = useCartStore((s) => s.tipoPedido);
  const tableSession = useTableSessionStore((s) => s.session);
  const deferredBranch = useTableSessionStore((s) => s.deferredBranch);
  const refreshBranchConfig = useBranchConfigStore((state) => state.refresh);
  const { data: favorites, toggle } = useFavorites();

  const isFav = favorites?.some((f: any) => f.id === platillo?.id);
  const [cantidad, setCantidad] = useState(1);
  const [modsSel, setModsSel] = useState<ModificadorSeleccionado[]>([]);

  useEffect(() => {
    if (!platillo?.selector) return;
    const defaults: ModificadorSeleccionado[] = [];
    const omitted = platillo.selector.incluidas.filter(
      (item) => item.omitida_por_defecto || !item.seleccionada_por_defecto
    );
    if (omitted.length > 0) {
      defaults.push({
        modificador_id: -2,
        modificador_nombre: 'Incluidos',
        opciones: omitted.map((item) => ({
          opcion_id: item.id,
          opcion_nombre: item.nombre,
          precio_extra: 0,
          cantidad: 1,
          tipo_modificador: 'exclusion',
        })),
      });
    }
    const initialExtras = platillo.selector.extras.filter((item) => item.cantidad_inicial > 0);
    if (initialExtras.length > 0) {
      defaults.push({
        modificador_id: -1,
        modificador_nombre: 'Extras',
        opciones: initialExtras.map((item) => ({
          opcion_id: item.id,
          opcion_nombre: item.nombre,
          precio_extra: item.precio_unitario,
          cantidad: item.cantidad_inicial,
          tipo_modificador: 'extra',
        })),
      });
    }
    setModsSel(defaults);
  }, [platillo?.id, platillo?.selector]);

  function goBack() {
    router.back();
  }

  async function refreshProduct() {
    await Promise.all([
      parsedRestaurantId ? refreshBranchConfig(parsedRestaurantId, { force: true }) : Promise.resolve(),
      refetch(),
    ]).catch(() => undefined);
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
          cantidad: 1,
          tipo_modificador: opcion.tipo_modificador,
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
                cantidad: 1,
                tipo_modificador: opcion.tipo_modificador,
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

  function changeOptionQuantity(modId: number, optionId: number, delta: number, max: number) {
    setModsSel((current) => current.flatMap((group) => {
      if (group.modificador_id !== modId) return [group];
      const options = group.opciones.flatMap((option) => {
        if (option.opcion_id !== optionId) return [option];
        const next = Math.min(max, Math.max(0, Number(option.cantidad ?? 1) + delta));
        return next === 0 ? [] : [{ ...option, cantidad: next }];
      });
      return options.length === 0 ? [] : [{ ...group, opciones: options }];
    }));
  }

  function handleAddToCart() {
    if (!platillo) return;

    if (tipoPedido === 'eat_in' && !tableSession) {
      router.replace({
        pathname: '/table-scanner',
        params: {
          returnTo: '/(tabs)',
          mode: 'eat_in',
          branchId: deferredBranch?.id ? String(deferredBranch.id) : undefined,
        },
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
        params: {
          returnTo: '/(tabs)',
          mode: 'eat_in',
          branchId: deferredBranch?.id ? String(deferredBranch.id) : undefined,
        },
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
          <Button label="Volver al menú" onPress={goBack} fullWidth />
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

  const extrasUnit = modsSel.reduce(
    (sum, group) => sum + group.opciones.reduce(
      (inner, option) => inner + Number(option.precio_extra || 0) * Number(option.cantidad ?? 1),
      0
    ),
    0
  );
  const total = (platillo.precio + extrasUnit) * cantidad;
  const footerPadding = Math.max(insets.bottom, 18);

  return (
    <SafeAreaView style={styles.screen}>
      <View style={styles.body}>
        <ScrollView
          style={styles.scroll}
          contentContainerStyle={styles.scrollContent}
          showsVerticalScrollIndicator={false}
          bounces
          refreshControl={
            <RefreshControl
              refreshing={isRefetching}
              onRefresh={() => void refreshProduct()}
              tintColor={Colors.primary}
            />
          }
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

              {tipoPedido === 'eat_in' ? (
                <TableContextBanner session={tableSession} variant="chip" />
              ) : null}

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
                    {mod.categoria === 'exclusion'
                      ? 'Desmarca lo que deseas omitir'
                      : mod.tipo === 'radio'
                      ? 'Elige una opción'
                      : 'Selecciona las que quieras'}
                  </Text>

                  {mod.opciones.map((opcion) => {
                    const selMod = modsSel.find(
                      (m) => m.modificador_id === mod.id
                    );

                    const isOmittedOrSelected =
                      selMod?.opciones.some(
                        (o) => o.opcion_id === opcion.id
                      ) ?? false;
                    const isIncluded = opcion.tipo_modificador === 'exclusion';
                    const isSelected = isIncluded ? !isOmittedOrSelected : isOmittedOrSelected;
                    const canToggle = !isIncluded || opcion.puede_omitirse !== false;
                    const selectedOption = selMod?.opciones.find((o) => o.opcion_id === opcion.id);
                    const optionQuantity = Number(selectedOption?.cantidad ?? 1);
                    const maxQuantity = Math.max(1, Number(opcion.max_cantidad ?? 1));

                    return (
                      <TouchableOpacity
                        key={opcion.id}
                        style={[styles.opcionRow, !canToggle && styles.optionDisabled]}
                        disabled={!canToggle}
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

                        {isIncluded ? (
                          <Text style={[styles.includedBadge, !isSelected && styles.omittedBadge]}>
                            {isSelected ? 'Incluido' : 'Omitir'}
                          </Text>
                        ) : null}

                        {isSelected && maxQuantity > 1 ? (
                          <View style={styles.optionQty}>
                            <TouchableOpacity onPress={(event) => { event.stopPropagation(); changeOptionQuantity(mod.id, opcion.id, -1, maxQuantity); }}>
                              <Ionicons name="remove-circle-outline" size={22} color={Colors.text} />
                            </TouchableOpacity>
                            <Text style={styles.optionQtyText}>{optionQuantity}</Text>
                            <TouchableOpacity
                              disabled={optionQuantity >= maxQuantity}
                              onPress={(event) => { event.stopPropagation(); changeOptionQuantity(mod.id, opcion.id, 1, maxQuantity); }}
                            >
                              <Ionicons name="add-circle-outline" size={22} color={optionQuantity >= maxQuantity ? Colors.textMuted : Colors.text} />
                            </TouchableOpacity>
                          </View>
                        ) : null}

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
  optionDisabled: { opacity: 0.62 },
  includedBadge: {
    fontSize: 10,
    fontWeight: '800',
    color: '#2F6B4F',
    backgroundColor: '#E9F6EF',
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 999,
  },
  omittedBadge: {
    color: '#9A5B27',
    backgroundColor: '#FFF1E4',
  },
  optionQty: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 5,
  },
  optionQtyText: {
    minWidth: 18,
    textAlign: 'center',
    fontSize: 13,
    fontWeight: '800',
    color: Colors.text,
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
