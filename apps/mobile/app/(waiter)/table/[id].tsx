import React, { useMemo, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  FlatList,
  Modal,
  Pressable,
  SafeAreaView,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import { Image } from 'expo-image';
import { Ionicons } from '@expo/vector-icons';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useQuery } from '@tanstack/react-query';
import type { Modificador, ModificadorSeleccionado, OpcionModificador, Platillo } from '@amare/types';
import { getCategories, getDishById, getDishes } from '../../../services/menu.service';
import { getApiError } from '../../../services/api';
import {
  closeWaiterAccount,
  createWaiterOrder,
  getWaiterAccount,
  type WaiterPaymentMethod,
} from '../../../services/waiter.service';
import { useWaiterCartStore } from '../../../store/waiter-cart.store';

const PLACEHOLDER_FOOD = require('../../../assets/placeholder-food.jpg');

function money(value: unknown): number {
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : 0;
}

function getSelectedExtras(modificadores: ModificadorSeleccionado[]) {
  return modificadores.flatMap((mod) =>
    mod.opciones.map((opcion) => ({
      key: `${mod.modificador_id}-${opcion.opcion_id}`,
      nombre: opcion.opcion_nombre,
      precio: money(opcion.precio_extra),
    }))
  );
}

function unitPrice(platillo: Platillo, modificadores: ModificadorSeleccionado[]): number {
  return money(platillo.precio) + getSelectedExtras(modificadores).reduce((sum, extra) => sum + extra.precio, 0);
}

export default function WaiterTableScreen() {
  const router = useRouter();
  const params = useLocalSearchParams<{
    id: string;
    restaurantId: string;
    tableLabel?: string;
    clienteNombre?: string;
    meseroNombre?: string;
    supportMode?: string;
  }>();
  const tableId = Number(params.id);
  const restaurantId = Number(params.restaurantId);
  const tableLabel = params.tableLabel || `Mesa ${tableId}`;
  const initialCustomerName = params.clienteNombre || '';
  const initialWaiterName = params.meseroNombre || '';
  const supportMode = params.supportMode === '1';

  const [menuVisible, setMenuVisible] = useState(false);
  const [closeVisible, setCloseVisible] = useState(false);
  const [paymentMethod, setPaymentMethod] = useState<WaiterPaymentMethod>('efectivo');
  const [selectedCategoryId, setSelectedCategoryId] = useState<number | null>(null);
  const [selectedProduct, setSelectedProduct] = useState<Platillo | null>(null);
  const [productLoading, setProductLoading] = useState(false);
  const [quantity, setQuantity] = useState(1);
  const [notes, setNotes] = useState('');
  const [selectedMods, setSelectedMods] = useState<ModificadorSeleccionado[]>([]);
  const [sending, setSending] = useState(false);
  const [closing, setClosing] = useState(false);

  const waiterCart = useWaiterCartStore();
  const cartItems = waiterCart.tableId === tableId && waiterCart.restaurantId === restaurantId ? waiterCart.items : [];
  const cartTotal = waiterCart.tableId === tableId && waiterCart.restaurantId === restaurantId ? waiterCart.total : 0;

  const accountQuery = useQuery({
    queryKey: ['waiter', 'account', restaurantId, tableId],
    queryFn: () => getWaiterAccount(tableId, restaurantId),
    enabled: Number.isFinite(tableId) && Number.isFinite(restaurantId),
  });
  const categoriesQuery = useQuery({
    queryKey: ['waiter', 'categories', restaurantId],
    queryFn: () => getCategories(restaurantId),
    enabled: Number.isFinite(restaurantId),
  });
  const dishesQuery = useQuery({
    queryKey: ['waiter', 'dishes', restaurantId, selectedCategoryId],
    queryFn: () => getDishes(restaurantId, selectedCategoryId ? { categoria_id: selectedCategoryId } : undefined),
    enabled: Number.isFinite(restaurantId),
  });

  const account = accountQuery.data;
  const customerName = account?.cliente_nombre || initialCustomerName || waiterCart.clienteNombre || 'Comensal';
  const waiterName = account?.mesero_nombre || account?.table?.mesero_nombre || initialWaiterName || 'Mesero';
  const sentItems = account?.items ?? [];
  const sentTotal = account?.total ?? 0;

  const productUnitPrice = useMemo(
    () => (selectedProduct ? unitPrice(selectedProduct, selectedMods) : 0),
    [selectedProduct, selectedMods]
  );

  async function openProduct(product: Platillo) {
    try {
      setProductLoading(true);
      setSelectedProduct(null);
      setQuantity(1);
      setNotes('');
      setSelectedMods([]);
      const fullProduct = await getDishById(restaurantId, product.id);
      setSelectedProduct(fullProduct);
    } catch (error) {
      Alert.alert('Producto', getApiError(error));
    } finally {
      setProductLoading(false);
    }
  }

  function toggleOption(mod: Modificador, option: OpcionModificador) {
    const nextOption = {
      opcion_id: option.id,
      opcion_nombre: option.nombre,
      precio_extra: money(option.precio_extra),
    };

    setSelectedMods((current) => {
      if (mod.tipo === 'radio') {
        return [
          ...current.filter((item) => item.modificador_id !== mod.id),
          {
            modificador_id: mod.id,
            modificador_nombre: mod.nombre,
            opciones: [nextOption],
          },
        ];
      }

      const existing = current.find((item) => item.modificador_id === mod.id);
      if (!existing) {
        return [
          ...current,
          {
            modificador_id: mod.id,
            modificador_nombre: mod.nombre,
            opciones: [nextOption],
          },
        ];
      }

      const alreadySelected = existing.opciones.some((item) => item.opcion_id === option.id);
      const options = alreadySelected
        ? existing.opciones.filter((item) => item.opcion_id !== option.id)
        : [...existing.opciones, nextOption];

      if (options.length === 0) {
        return current.filter((item) => item.modificador_id !== mod.id);
      }

      return current.map((item) => (item.modificador_id === mod.id ? { ...item, opciones: options } : item));
    });
  }

  function isOptionSelected(modId: number, optionId: number): boolean {
    return selectedMods.some((mod) => mod.modificador_id === modId && mod.opciones.some((option) => option.opcion_id === optionId));
  }

  function addSelectedProduct() {
    if (!selectedProduct) return;

    waiterCart.addItem({
      tableId,
      restaurantId,
      clienteNombre: customerName,
      platillo: selectedProduct,
      cantidad: quantity,
      modificadores: selectedMods,
      notas: notes.trim(),
    });
    setSelectedProduct(null);
  }

  async function sendOrder() {
    if (cartItems.length === 0) {
      Alert.alert('Comanda vacia', 'Agrega productos antes de enviar a cocina.');
      return;
    }

    try {
      setSending(true);
      await createWaiterOrder({
        tableId,
        restaurantId,
        clienteNombre: customerName,
        items: cartItems.map((item) => ({
          platillo_id: item.platillo.id,
          cantidad: item.cantidad,
          precio_unit: item.precio_unitario,
          notas: item.notas,
          modificadores: item.modificadores,
        })),
      });
      waiterCart.clear();
      setMenuVisible(false);
      await accountQuery.refetch();
      Alert.alert('Comanda enviada', 'El pedido fue enviado a cocina.');
    } catch (error) {
      Alert.alert('No se pudo enviar', getApiError(error));
    } finally {
      setSending(false);
    }
  }

  async function closeAccount() {
    if (cartItems.length > 0) {
      Alert.alert('Comanda pendiente', 'Envia o vacia la comanda pendiente antes de cerrar la cuenta.');
      return;
    }
    if (sentItems.length === 0) {
      Alert.alert('Cuenta vacia', 'No hay productos enviados para cerrar esta cuenta.');
      return;
    }

    try {
      setClosing(true);
      await closeWaiterAccount({
        tableId,
        restaurantId,
        metodoPago: paymentMethod,
      });
      waiterCart.clear();
      setCloseVisible(false);
      Alert.alert('Cuenta cerrada', 'La cuenta se marco como pagada y la mesa quedo disponible.', [
        {
          text: 'Aceptar',
          onPress: () => router.replace('/(waiter)' as never),
        },
      ]);
    } catch (error) {
      Alert.alert('No se pudo cerrar', getApiError(error));
    } finally {
      setClosing(false);
    }
  }

  return (
    <SafeAreaView style={styles.safe}>
      <View style={styles.header}>
        <TouchableOpacity style={styles.iconButton} onPress={() => router.back()} activeOpacity={0.8}>
          <Ionicons name="arrow-back" size={22} color="#111827" />
        </TouchableOpacity>
        <View style={styles.headerCopy}>
          <Text style={styles.title}>{tableLabel}</Text>
          <Text style={styles.subtitle}>{customerName}</Text>
        </View>
        <TouchableOpacity style={styles.iconButton} onPress={() => accountQuery.refetch()} activeOpacity={0.8}>
          <Ionicons name="refresh" size={20} color="#111827" />
        </TouchableOpacity>
      </View>

      <ScrollView contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        <View style={styles.accountCard}>
          <Text style={styles.cardLabel}>Cuenta abierta</Text>
          <Text style={styles.totalText}>${(sentTotal + cartTotal).toFixed(2)}</Text>
          <View style={styles.statsRow}>
            <Text style={styles.statText}>{account?.orders_count ?? 0} comandas enviadas</Text>
            <Text style={styles.statText}>{cartItems.length} pendientes</Text>
          </View>
          <View style={styles.accountMeta}>
            <Text style={styles.accountMetaText}>Comensal: {customerName}</Text>
            <Text style={styles.accountMetaText}>Mesero: {waiterName}</Text>
          </View>
          {supportMode ? (
            <View style={styles.supportPill}>
              <Ionicons name="people-outline" size={14} color="#FBBF24" />
              <Text style={styles.supportPillText}>Modo apoyo</Text>
            </View>
          ) : null}
        </View>

        <TouchableOpacity style={styles.addButton} activeOpacity={0.88} onPress={() => setMenuVisible(true)}>
          <Ionicons name="restaurant-outline" size={20} color="#FFFFFF" />
          <Text style={styles.addButtonText}>Agregar productos</Text>
        </TouchableOpacity>

        <TouchableOpacity
          style={[styles.closeButton, (sentItems.length === 0 || cartItems.length > 0) && styles.closeButtonDisabled]}
          activeOpacity={0.88}
          onPress={() => setCloseVisible(true)}
          disabled={sentItems.length === 0 || cartItems.length > 0}
        >
          <Ionicons name="cash-outline" size={20} color="#111827" />
          <Text style={styles.closeButtonText}>Cerrar cuenta</Text>
        </TouchableOpacity>

        {cartItems.length > 0 ? (
          <View style={styles.section}>
            <View style={styles.sectionHeader}>
              <Text style={styles.sectionTitle}>Comanda pendiente</Text>
              <TouchableOpacity onPress={() => waiterCart.clear()}>
                <Text style={styles.clearText}>Vaciar</Text>
              </TouchableOpacity>
            </View>
            {cartItems.map((item) => (
              <View key={item.id} style={styles.lineItem}>
                <View style={styles.lineCopy}>
                  <Text style={styles.lineName}>{item.cantidad}x {item.platillo.nombre}</Text>
                  {getSelectedExtras(item.modificadores).map((extra) => (
                    <Text key={extra.key} style={styles.extraText}>+ {extra.nombre} ${extra.precio.toFixed(2)}</Text>
                  ))}
                </View>
                <View style={styles.lineActions}>
                  <Text style={styles.linePrice}>${item.subtotal.toFixed(2)}</Text>
                  <View style={styles.qtyRow}>
                    <TouchableOpacity onPress={() => waiterCart.updateQty(item.id, item.cantidad - 1)} style={styles.qtyButton}>
                      <Ionicons name={item.cantidad > 1 ? 'remove' : 'trash-outline'} size={14} color="#111827" />
                    </TouchableOpacity>
                    <TouchableOpacity onPress={() => waiterCart.updateQty(item.id, item.cantidad + 1)} style={styles.qtyButton}>
                      <Ionicons name="add" size={14} color="#111827" />
                    </TouchableOpacity>
                  </View>
                </View>
              </View>
            ))}
            <TouchableOpacity style={styles.sendButton} activeOpacity={0.88} onPress={sendOrder} disabled={sending}>
              {sending ? <ActivityIndicator color="#FFFFFF" /> : <Text style={styles.sendButtonText}>Enviar comanda · ${cartTotal.toFixed(2)}</Text>}
            </TouchableOpacity>
          </View>
        ) : null}

        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Productos pedidos</Text>
          {accountQuery.isLoading ? (
            <ActivityIndicator color="#111827" style={{ marginTop: 20 }} />
          ) : sentItems.length === 0 ? (
            <Text style={styles.emptyText}>Aun no hay productos enviados a cocina.</Text>
          ) : (
            sentItems.map((item) => (
              <View key={`${item.pedido_id}-${item.id}`} style={styles.sentItem}>
                <View style={styles.lineCopy}>
                  <Text style={styles.lineName}>{item.cantidad}x {item.nombre}</Text>
                  <Text style={styles.sentMeta}>{item.pedido_folio ?? 'Comanda'} · {item.estado ?? 'pendiente'}</Text>
                </View>
                <Text style={styles.linePrice}>${item.subtotal.toFixed(2)}</Text>
              </View>
            ))
          )}
        </View>
      </ScrollView>

      <Modal visible={menuVisible} animationType="slide" onRequestClose={() => setMenuVisible(false)}>
        <SafeAreaView style={styles.modalSafe}>
          <View style={styles.header}>
            <TouchableOpacity style={styles.iconButton} onPress={() => setMenuVisible(false)} activeOpacity={0.8}>
              <Ionicons name="close" size={22} color="#111827" />
            </TouchableOpacity>
            <View style={styles.headerCopy}>
              <Text style={styles.title}>Menu</Text>
              <Text style={styles.subtitle}>{tableLabel}</Text>
            </View>
            <View style={styles.iconButtonPlaceholder} />
          </View>

          <FlatList
            horizontal
            data={[{ id: 0, nombre: 'Todo' }, ...(categoriesQuery.data ?? [])]}
            keyExtractor={(category) => String(category.id)}
            contentContainerStyle={styles.categoryList}
            showsHorizontalScrollIndicator={false}
            renderItem={({ item }) => {
              const active = (selectedCategoryId ?? 0) === item.id;
              return (
                <TouchableOpacity
                  style={[styles.categoryChip, active && styles.categoryChipActive]}
                  onPress={() => setSelectedCategoryId(item.id === 0 ? null : item.id)}
                >
                  <Text style={[styles.categoryText, active && styles.categoryTextActive]}>{item.nombre}</Text>
                </TouchableOpacity>
              );
            }}
          />

          {dishesQuery.isLoading ? (
            <ActivityIndicator color="#111827" style={{ marginTop: 24 }} />
          ) : (
            <FlatList
              data={dishesQuery.data ?? []}
              keyExtractor={(dish) => String(dish.id)}
              contentContainerStyle={styles.productList}
              renderItem={({ item }) => (
                <TouchableOpacity style={styles.productRow} activeOpacity={0.86} onPress={() => openProduct(item)}>
                  <Image source={item.imagen ? { uri: item.imagen } : PLACEHOLDER_FOOD} style={styles.productImage} contentFit="cover" />
                  <View style={styles.productCopy}>
                    <Text style={styles.productName}>{item.nombre}</Text>
                    <Text style={styles.productDescription} numberOfLines={2}>{item.descripcion ?? 'Sin descripcion'}</Text>
                  </View>
                  <Text style={styles.productPrice}>${money(item.precio).toFixed(2)}</Text>
                </TouchableOpacity>
              )}
            />
          )}

          {Boolean(selectedProduct) || productLoading ? (
            <Pressable style={styles.productOverlay} onPress={() => !productLoading && setSelectedProduct(null)}>
              <Pressable style={styles.productModal} onPress={(event) => event.stopPropagation()}>
                {productLoading || !selectedProduct ? (
                  <ActivityIndicator color="#111827" />
                ) : (
                  <>
                    <Text style={styles.productModalTitle}>{selectedProduct.nombre}</Text>
                    <Text style={styles.productModalPrice}>Base ${money(selectedProduct.precio).toFixed(2)}</Text>

                    <ScrollView style={styles.modifierScroll} showsVerticalScrollIndicator={false}>
                      {(selectedProduct.modificadores ?? []).map((mod) => (
                        <View key={mod.id} style={styles.modifierBlock}>
                          <Text style={styles.modifierTitle}>{mod.nombre}</Text>
                          {mod.opciones.map((option) => {
                            const selected = isOptionSelected(mod.id, option.id);
                            return (
                              <TouchableOpacity
                                key={option.id}
                                style={styles.optionRow}
                                activeOpacity={0.85}
                                onPress={() => toggleOption(mod, option)}
                              >
                                <View style={[styles.checkbox, selected && styles.checkboxActive]}>
                                  {selected ? <Ionicons name="checkmark" size={16} color="#FFFFFF" /> : null}
                                </View>
                                <Text style={styles.optionName}>{option.nombre}</Text>
                                <Text style={styles.optionPrice}>+${money(option.precio_extra).toFixed(2)}</Text>
                              </TouchableOpacity>
                            );
                          })}
                        </View>
                      ))}

                      <TextInput
                        value={notes}
                        onChangeText={setNotes}
                        placeholder="Notas para cocina"
                        placeholderTextColor="#9CA3AF"
                        style={styles.notesInput}
                        multiline
                      />
                    </ScrollView>

                    <View style={styles.productFooter}>
                      <View style={styles.quantityPill}>
                        <TouchableOpacity onPress={() => setQuantity((current) => Math.max(1, current - 1))} style={styles.qtyButton}>
                          <Ionicons name="remove" size={16} color="#111827" />
                        </TouchableOpacity>
                        <Text style={styles.quantityText}>{quantity}</Text>
                        <TouchableOpacity onPress={() => setQuantity((current) => current + 1)} style={styles.qtyButton}>
                          <Ionicons name="add" size={16} color="#111827" />
                        </TouchableOpacity>
                      </View>
                      <TouchableOpacity style={styles.addToCartButton} onPress={addSelectedProduct} activeOpacity={0.88}>
                        <Text style={styles.addToCartText}>Agregar - ${(productUnitPrice * quantity).toFixed(2)}</Text>
                      </TouchableOpacity>
                    </View>
                  </>
                )}
              </Pressable>
            </Pressable>
          ) : null}
        </SafeAreaView>
      </Modal>

      <Modal visible={closeVisible} transparent animationType="fade" onRequestClose={() => setCloseVisible(false)}>
        <Pressable style={styles.closeOverlay} onPress={() => setCloseVisible(false)}>
          <Pressable style={styles.closeModal} onPress={(event) => event.stopPropagation()}>
            <Text style={styles.closeTitle}>Cerrar cuenta</Text>
            <Text style={styles.closeText}>Selecciona el metodo de pago para liberar {tableLabel}.</Text>
            <Text style={styles.closeTotal}>${sentTotal.toFixed(2)}</Text>

            {([
              ['efectivo', 'Efectivo'],
              ['tarjeta', 'Tarjeta'],
              ['transferencia', 'Transferencia'],
            ] as Array<[WaiterPaymentMethod, string]>).map(([value, label]) => {
              const selected = paymentMethod === value;
              return (
                <TouchableOpacity
                  key={value}
                  style={[styles.paymentOption, selected && styles.paymentOptionActive]}
                  onPress={() => setPaymentMethod(value)}
                  activeOpacity={0.85}
                >
                  <Ionicons name={selected ? 'radio-button-on' : 'radio-button-off'} size={20} color={selected ? '#111827' : '#9CA3AF'} />
                  <Text style={styles.paymentOptionText}>{label}</Text>
                </TouchableOpacity>
              );
            })}

            <View style={styles.closeActions}>
              <TouchableOpacity style={styles.cancelCloseButton} onPress={() => setCloseVisible(false)} disabled={closing}>
                <Text style={styles.cancelCloseText}>Cancelar</Text>
              </TouchableOpacity>
              <TouchableOpacity style={styles.confirmCloseButton} onPress={closeAccount} disabled={closing}>
                {closing ? <ActivityIndicator color="#FFFFFF" /> : <Text style={styles.confirmCloseText}>Cerrar</Text>}
              </TouchableOpacity>
            </View>
          </Pressable>
        </Pressable>
      </Modal>

    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: {
    flex: 1,
    backgroundColor: '#F8FAFC',
  },
  modalSafe: {
    flex: 1,
    backgroundColor: '#FFFFFF',
  },
  header: {
    paddingHorizontal: 18,
    paddingVertical: 12,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  iconButton: {
    width: 42,
    height: 42,
    borderRadius: 21,
    backgroundColor: '#FFFFFF',
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: '#E5E7EB',
  },
  iconButtonPlaceholder: {
    width: 42,
  },
  headerCopy: {
    flex: 1,
  },
  title: {
    fontSize: 22,
    fontWeight: '900',
    color: '#111827',
  },
  subtitle: {
    marginTop: 2,
    fontSize: 13,
    color: '#6B7280',
    fontWeight: '700',
  },
  content: {
    paddingHorizontal: 18,
    paddingBottom: 28,
    gap: 16,
  },
  accountCard: {
    borderRadius: 22,
    backgroundColor: '#111827',
    padding: 20,
  },
  cardLabel: {
    color: '#CBD5E1',
    fontWeight: '800',
  },
  totalText: {
    marginTop: 8,
    fontSize: 34,
    fontWeight: '900',
    color: '#FFFFFF',
  },
  statsRow: {
    marginTop: 12,
    flexDirection: 'row',
    gap: 12,
  },
  statText: {
    color: '#E5E7EB',
    fontSize: 13,
    fontWeight: '700',
  },
  accountMeta: {
    marginTop: 14,
    gap: 4,
  },
  accountMetaText: {
    color: '#E5E7EB',
    fontSize: 13,
    fontWeight: '800',
  },
  supportPill: {
    alignSelf: 'flex-start',
    marginTop: 14,
    borderRadius: 999,
    paddingHorizontal: 10,
    paddingVertical: 6,
    backgroundColor: 'rgba(251, 191, 36, 0.14)',
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  supportPillText: {
    color: '#FBBF24',
    fontSize: 12,
    fontWeight: '900',
  },
  addButton: {
    minHeight: 54,
    borderRadius: 18,
    backgroundColor: '#2563EB',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
  },
  addButtonText: {
    fontSize: 16,
    fontWeight: '900',
    color: '#FFFFFF',
  },
  closeButton: {
    minHeight: 54,
    borderRadius: 18,
    backgroundColor: '#FFFFFF',
    borderWidth: 1,
    borderColor: '#D1D5DB',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
  },
  closeButtonDisabled: {
    opacity: 0.45,
  },
  closeButtonText: {
    fontSize: 16,
    fontWeight: '900',
    color: '#111827',
  },
  section: {
    borderRadius: 20,
    backgroundColor: '#FFFFFF',
    padding: 16,
    borderWidth: 1,
    borderColor: '#EEF2F7',
  },
  sectionHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 8,
  },
  sectionTitle: {
    fontSize: 17,
    fontWeight: '900',
    color: '#111827',
  },
  clearText: {
    color: '#DC2626',
    fontWeight: '800',
  },
  lineItem: {
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderBottomColor: '#F3F4F6',
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: 12,
  },
  sentItem: {
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderBottomColor: '#F3F4F6',
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: 12,
  },
  lineCopy: {
    flex: 1,
  },
  lineName: {
    fontSize: 15,
    fontWeight: '900',
    color: '#111827',
  },
  extraText: {
    marginTop: 3,
    fontSize: 12,
    color: '#6B7280',
    fontWeight: '700',
  },
  sentMeta: {
    marginTop: 4,
    fontSize: 12,
    color: '#6B7280',
    fontWeight: '700',
  },
  lineActions: {
    alignItems: 'flex-end',
    gap: 8,
  },
  linePrice: {
    fontSize: 15,
    fontWeight: '900',
    color: '#111827',
  },
  qtyRow: {
    flexDirection: 'row',
    gap: 6,
  },
  qtyButton: {
    width: 30,
    height: 30,
    borderRadius: 15,
    backgroundColor: '#F3F4F6',
    alignItems: 'center',
    justifyContent: 'center',
  },
  sendButton: {
    marginTop: 14,
    minHeight: 52,
    borderRadius: 16,
    backgroundColor: '#111827',
    alignItems: 'center',
    justifyContent: 'center',
  },
  sendButtonText: {
    color: '#FFFFFF',
    fontWeight: '900',
    fontSize: 15,
  },
  emptyText: {
    marginTop: 12,
    color: '#6B7280',
    fontWeight: '700',
  },
  categoryList: {
    paddingHorizontal: 18,
    paddingBottom: 12,
    gap: 10,
  },
  categoryChip: {
    paddingHorizontal: 14,
    paddingVertical: 10,
    borderRadius: 999,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    backgroundColor: '#FFFFFF',
  },
  categoryChipActive: {
    backgroundColor: '#111827',
    borderColor: '#111827',
  },
  categoryText: {
    color: '#4B5563',
    fontWeight: '900',
  },
  categoryTextActive: {
    color: '#FFFFFF',
  },
  productList: {
    paddingHorizontal: 18,
    paddingBottom: 24,
  },
  productRow: {
    minHeight: 96,
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderBottomColor: '#F3F4F6',
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  productImage: {
    width: 72,
    height: 72,
    borderRadius: 14,
    backgroundColor: '#F3F4F6',
  },
  productCopy: {
    flex: 1,
  },
  productName: {
    fontSize: 15,
    fontWeight: '900',
    color: '#111827',
  },
  productDescription: {
    marginTop: 4,
    fontSize: 12,
    color: '#6B7280',
    lineHeight: 17,
  },
  productPrice: {
    fontSize: 14,
    fontWeight: '900',
    color: '#111827',
  },
  productOverlay: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: 'rgba(17, 24, 39, 0.48)',
    justifyContent: 'flex-end',
  },
  productModal: {
    maxHeight: '86%',
    borderTopLeftRadius: 26,
    borderTopRightRadius: 26,
    backgroundColor: '#FFFFFF',
    padding: 18,
  },
  productModalTitle: {
    fontSize: 21,
    fontWeight: '900',
    color: '#111827',
  },
  productModalPrice: {
    marginTop: 4,
    color: '#6B7280',
    fontWeight: '800',
  },
  modifierScroll: {
    marginTop: 12,
  },
  modifierBlock: {
    marginBottom: 14,
  },
  modifierTitle: {
    fontSize: 14,
    fontWeight: '900',
    color: '#111827',
    marginBottom: 8,
  },
  optionRow: {
    minHeight: 44,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  checkbox: {
    width: 25,
    height: 25,
    borderRadius: 8,
    borderWidth: 1.4,
    borderColor: '#D1D5DB',
    alignItems: 'center',
    justifyContent: 'center',
  },
  checkboxActive: {
    backgroundColor: '#111827',
    borderColor: '#111827',
  },
  optionName: {
    flex: 1,
    fontSize: 14,
    fontWeight: '800',
    color: '#111827',
  },
  optionPrice: {
    fontSize: 13,
    fontWeight: '900',
    color: '#6B7280',
  },
  notesInput: {
    minHeight: 74,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: '#D1D5DB',
    paddingHorizontal: 12,
    paddingVertical: 10,
    color: '#111827',
    fontWeight: '700',
    backgroundColor: '#F9FAFB',
  },
  productFooter: {
    marginTop: 14,
    flexDirection: 'row',
    gap: 10,
  },
  quantityPill: {
    flexDirection: 'row',
    alignItems: 'center',
    borderRadius: 18,
    backgroundColor: '#F3F4F6',
    padding: 5,
  },
  quantityText: {
    minWidth: 34,
    textAlign: 'center',
    fontSize: 16,
    fontWeight: '900',
    color: '#111827',
  },
  addToCartButton: {
    flex: 1,
    borderRadius: 18,
    backgroundColor: '#111827',
    alignItems: 'center',
    justifyContent: 'center',
  },
  addToCartText: {
    color: '#FFFFFF',
    fontWeight: '900',
    fontSize: 15,
  },
  closeOverlay: {
    flex: 1,
    backgroundColor: 'rgba(17, 24, 39, 0.48)',
    justifyContent: 'center',
    padding: 20,
  },
  closeModal: {
    borderRadius: 24,
    backgroundColor: '#FFFFFF',
    padding: 20,
  },
  closeTitle: {
    fontSize: 22,
    fontWeight: '900',
    color: '#111827',
  },
  closeText: {
    marginTop: 8,
    color: '#6B7280',
    fontWeight: '700',
    lineHeight: 20,
  },
  closeTotal: {
    marginTop: 14,
    marginBottom: 14,
    fontSize: 30,
    fontWeight: '900',
    color: '#111827',
  },
  paymentOption: {
    minHeight: 50,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    paddingHorizontal: 14,
    marginBottom: 10,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  paymentOptionActive: {
    borderColor: '#111827',
    backgroundColor: '#F9FAFB',
  },
  paymentOptionText: {
    fontSize: 15,
    fontWeight: '900',
    color: '#111827',
  },
  closeActions: {
    flexDirection: 'row',
    gap: 10,
    marginTop: 8,
  },
  cancelCloseButton: {
    flex: 1,
    minHeight: 50,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: '#D1D5DB',
    alignItems: 'center',
    justifyContent: 'center',
  },
  cancelCloseText: {
    fontWeight: '900',
    color: '#111827',
  },
  confirmCloseButton: {
    flex: 1,
    minHeight: 50,
    borderRadius: 16,
    backgroundColor: '#111827',
    alignItems: 'center',
    justifyContent: 'center',
  },
  confirmCloseText: {
    fontWeight: '900',
    color: '#FFFFFF',
  },
});
