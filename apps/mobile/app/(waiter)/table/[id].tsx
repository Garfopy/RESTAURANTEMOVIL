import React, { useEffect, useMemo, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  FlatList,
  Modal,
  Platform,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
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
import { SplitAccountModal } from '../../../components/waiter/SplitAccountModal';

const PLACEHOLDER_FOOD = require('../../../assets/placeholder-food.jpg');

type IconName = keyof typeof Ionicons.glyphMap;
type MenuCategory = {
  id: number;
  nombre: string;
  total_platillos?: number;
};

function money(value: unknown): number {
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : 0;
}

function formatMoney(value: unknown): string {
  return `$${money(value).toFixed(2)}`;
}

function getSelectedExtras(modificadores: ModificadorSeleccionado[]) {
  return modificadores.flatMap((mod) =>
    mod.opciones.map((opcion) => ({
      key: `${mod.modificador_id}-${opcion.opcion_id}`,
      nombre: opcion.opcion_nombre,
      cantidad: Math.max(1, Number(opcion.cantidad ?? 1)),
      precio: money(opcion.precio_extra) * Math.max(1, Number(opcion.cantidad ?? 1)),
    }))
  );
}

function unitPrice(platillo: Platillo, modificadores: ModificadorSeleccionado[]): number {
  return money(platillo.precio) + getSelectedExtras(modificadores).reduce((sum, extra) => sum + extra.precio, 0);
}

function getPersistedModifierLabels(modifiers: unknown[] | undefined): string[] {
  return (modifiers ?? []).flatMap((modifier: any) => {
    if (Array.isArray(modifier?.opciones)) {
      return modifier.opciones.map((option: any) =>
        `${option.opcion_nombre}${Number(option.cantidad || 1) > 1 ? ` x${option.cantidad}` : ''}`
      );
    }
    if (!modifier?.nombre) return [];
    const prefix = modifier.tipo === 'exclusion' ? 'Sin ' : '+ ';
    return [`${prefix}${modifier.nombre}${Number(modifier.cantidad || 1) > 1 ? ` x${modifier.cantidad}` : ''}`];
  });
}

function categoryIcon(name: string): IconName {
  const normalized = name.toLowerCase();
  if (normalized.includes('bebida') || normalized.includes('bar') || normalized.includes('drink')) return 'wine-outline';
  if (normalized.includes('postre') || normalized.includes('dulce')) return 'ice-cream-outline';
  if (normalized.includes('cafe') || normalized.includes('desayuno')) return 'cafe-outline';
  if (normalized.includes('todo')) return 'apps-outline';
  return 'restaurant-outline';
}

function paymentIcon(method: WaiterPaymentMethod): IconName {
  if (method === 'tarjeta') return 'card-outline';
  if (method === 'transferencia') return 'swap-horizontal-outline';
  return 'cash-outline';
}

export default function WaiterTableScreen() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
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
  const [splitVisible, setSplitVisible] = useState(false);
  const [paymentMethod, setPaymentMethod] = useState<WaiterPaymentMethod>('efectivo');
  const [selectedCategoryId, setSelectedCategoryId] = useState<number | null>(null);
  const [menuSearch, setMenuSearch] = useState('');
  const [selectedProduct, setSelectedProduct] = useState<Platillo | null>(null);
  const [productLoading, setProductLoading] = useState(false);
  const [quantity, setQuantity] = useState(1);
  const [notes, setNotes] = useState('');
  const [selectedMods, setSelectedMods] = useState<ModificadorSeleccionado[]>([]);
  const [sending, setSending] = useState(false);
  const [closing, setClosing] = useState(false);
  const resumedSplitId = useRef<number | null>(null);

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
    queryKey: ['waiter', 'dishes', restaurantId, selectedCategoryId, menuSearch.trim()],
    queryFn: () => {
      const q = menuSearch.trim();
      return getDishes(restaurantId, {
        ...(q ? { q } : {}),
        ...(!q && selectedCategoryId ? { categoria_id: selectedCategoryId } : {}),
      });
    },
    enabled: Number.isFinite(restaurantId),
  });

  const account = accountQuery.data;
  const customerName = account?.cliente_nombre || initialCustomerName || waiterCart.clienteNombre || 'Comensal';
  const waiterName = account?.mesero_nombre || account?.table?.mesero_nombre || initialWaiterName || 'Mesero';
  const sentItems = account?.items ?? [];
  const sentTotal = account?.total ?? 0;
  const activeSplit = account?.active_split ?? null;
  const totalDue = sentTotal + cartTotal;
  const hasPendingCart = cartItems.length > 0;
  const canCloseAccount = sentItems.length > 0 && !hasPendingCart;

  useEffect(() => {
    if (!activeSplit || resumedSplitId.current === activeSplit.id) return;
    resumedSplitId.current = activeSplit.id;
    setSplitVisible(true);
  }, [activeSplit]);

  const menuCategories = useMemo<MenuCategory[]>(
    () => [{ id: 0, nombre: 'Todo' }, ...((categoriesQuery.data ?? []) as MenuCategory[])],
    [categoriesQuery.data]
  );

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
      cantidad: 1,
      tipo_modificador: option.tipo_modificador,
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

  function changeOptionQuantity(modId: number, optionId: number, delta: number, max: number) {
    setSelectedMods((current) => current.flatMap((group) => {
      if (group.modificador_id !== modId) return [group];
      const options = group.opciones.flatMap((option) => {
        if (option.opcion_id !== optionId) return [option];
        const next = Math.min(max, Math.max(0, Number(option.cantidad ?? 1) + delta));
        return next === 0 ? [] : [{ ...option, cantidad: next }];
      });
      return options.length === 0 ? [] : [{ ...group, opciones: options }];
    }));
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

  function openCloseFlow() {
    if (activeSplit) {
      setSplitVisible(true);
      return;
    }
    Alert.alert('Cerrar cuenta', '¿Como deseas cobrar esta mesa?', [
      { text: 'Cancelar', style: 'cancel' },
      { text: 'Una sola cuenta', onPress: () => setCloseVisible(true) },
      { text: 'Cuentas separadas', onPress: () => setSplitVisible(true) },
    ]);
  }

  function finishSplitPayment() {
    waiterCart.clear();
    setSplitVisible(false);
    Alert.alert('Mesa liquidada', 'Todas las cuentas fueron pagadas y la mesa quedo disponible.', [
      { text: 'Aceptar', onPress: () => router.replace('/(waiter)' as never) },
    ]);
  }

  function renderPendingItem(item: (typeof cartItems)[number]) {
    return (
      <View key={item.id} style={styles.pendingItem}>
        <View style={styles.itemQtyBadge}>
          <Text style={styles.itemQtyText}>{item.cantidad}</Text>
        </View>
        <View style={styles.lineCopy}>
          <Text style={styles.lineName} numberOfLines={2}>{item.platillo.nombre}</Text>
          {getSelectedExtras(item.modificadores).map((extra) => (
            <Text key={extra.key} style={styles.extraText} numberOfLines={1}>+ {extra.nombre} {formatMoney(extra.precio)}</Text>
          ))}
          {item.notas ? <Text style={styles.noteText} numberOfLines={2}>{item.notas}</Text> : null}
        </View>
        <View style={styles.lineActions}>
          <Text style={styles.linePrice}>{formatMoney(item.subtotal)}</Text>
          <View style={styles.qtyRow}>
            <TouchableOpacity onPress={() => waiterCart.updateQty(item.id, item.cantidad - 1)} style={styles.qtyButton}>
              <Ionicons name={item.cantidad > 1 ? 'remove' : 'trash-outline'} size={15} color="#111827" />
            </TouchableOpacity>
            <TouchableOpacity onPress={() => waiterCart.updateQty(item.id, item.cantidad + 1)} style={styles.qtyButton}>
              <Ionicons name="add" size={15} color="#111827" />
            </TouchableOpacity>
          </View>
        </View>
      </View>
    );
  }

  return (
    <SafeAreaView style={styles.safe}>
      <View style={styles.header}>
        <TouchableOpacity style={styles.iconButton} onPress={() => router.back()} activeOpacity={0.8}>
          <Ionicons name="arrow-back" size={22} color="#111827" />
        </TouchableOpacity>
        <View style={styles.headerCopy}>
          <Text style={styles.title} numberOfLines={1}>{tableLabel}</Text>
          <Text style={styles.subtitle} numberOfLines={1}>{customerName}</Text>
        </View>
        <TouchableOpacity style={styles.iconButton} onPress={() => accountQuery.refetch()} activeOpacity={0.8}>
          {accountQuery.isRefetching ? <ActivityIndicator size="small" color="#111827" /> : <Ionicons name="refresh" size={20} color="#111827" />}
        </TouchableOpacity>
      </View>

      <ScrollView
        contentContainerStyle={styles.content}
        showsVerticalScrollIndicator={false}
        refreshControl={<RefreshControl refreshing={accountQuery.isRefetching} onRefresh={() => accountQuery.refetch()} />}
      >
        <View style={styles.accountPanel}>
          <View style={styles.accountPanelTop}>
            <View style={styles.accountBadge}>
              <Ionicons name={supportMode ? 'people-outline' : 'receipt-outline'} size={16} color="#FFFFFF" />
              <Text style={styles.accountBadgeText}>{supportMode ? 'Modo apoyo' : 'Cuenta abierta'}</Text>
            </View>
            <Text style={styles.accountMetaText}>{account?.orders_count ?? 0} comandas</Text>
          </View>
          <Text style={styles.totalText}>{formatMoney(totalDue)}</Text>
          <View style={styles.accountStats}>
            <View style={styles.statBox}>
              <Text style={styles.statValue}>{sentItems.length}</Text>
              <Text style={styles.statLabel}>En cocina</Text>
            </View>
            <View style={styles.statBox}>
              <Text style={styles.statValue}>{cartItems.length}</Text>
              <Text style={styles.statLabel}>Pendientes</Text>
            </View>
            <View style={styles.statBox}>
              <Text style={styles.statValue}>{formatMoney(cartTotal)}</Text>
              <Text style={styles.statLabel}>Por enviar</Text>
            </View>
          </View>
          <View style={styles.accountPeople}>
            <Text style={styles.personLine} numberOfLines={1}>Comensal: {customerName}</Text>
            <Text style={styles.personLine} numberOfLines={1}>Mesero: {waiterName}</Text>
          </View>
        </View>

        <View style={styles.actionGrid}>
          <TouchableOpacity
            style={[styles.primaryActionButton, activeSplit && styles.actionButtonDisabled]}
            activeOpacity={0.88}
            onPress={() => setMenuVisible(true)}
            disabled={Boolean(activeSplit)}
          >
            <Ionicons name="restaurant-outline" size={20} color="#FFFFFF" />
            <Text style={styles.primaryActionText}>Agregar productos</Text>
          </TouchableOpacity>
          <TouchableOpacity
            style={[styles.secondaryActionButton, !canCloseAccount && styles.actionButtonDisabled]}
            activeOpacity={0.88}
            onPress={openCloseFlow}
            disabled={!canCloseAccount}
          >
            <Ionicons name="cash-outline" size={20} color="#111827" />
            <Text style={styles.secondaryActionText}>{activeSplit ? 'Continuar cobro' : 'Cerrar cuenta'}</Text>
          </TouchableOpacity>
        </View>

        <View style={[styles.section, hasPendingCart && styles.pendingSection]}>
          <View style={styles.sectionHeader}>
            <View>
              <Text style={styles.sectionTitle}>Comanda pendiente</Text>
              <Text style={styles.sectionSubtitle}>{cartItems.length} productos sin enviar</Text>
            </View>
            {cartItems.length > 0 ? (
              <TouchableOpacity style={styles.clearButton} onPress={() => waiterCart.clear()}>
                <Ionicons name="trash-outline" size={15} color="#B91C1C" />
                <Text style={styles.clearText}>Vaciar</Text>
              </TouchableOpacity>
            ) : null}
          </View>

          {cartItems.length > 0 ? (
            <>
              {cartItems.map(renderPendingItem)}
              <TouchableOpacity style={styles.sendButton} activeOpacity={0.88} onPress={sendOrder} disabled={sending}>
                {sending ? <ActivityIndicator color="#FFFFFF" /> : (
                  <>
                    <Ionicons name="send-outline" size={18} color="#FFFFFF" />
                    <Text style={styles.sendButtonText}>Enviar comanda - {formatMoney(cartTotal)}</Text>
                  </>
                )}
              </TouchableOpacity>
            </>
          ) : (
            <Text style={styles.emptyText}>Agrega alimentos desde el menu para preparar una comanda.</Text>
          )}
        </View>

        <View style={styles.section}>
          <View style={styles.sectionHeader}>
            <View>
              <Text style={styles.sectionTitle}>Productos enviados</Text>
              <Text style={styles.sectionSubtitle}>{sentItems.length} productos registrados</Text>
            </View>
            {accountQuery.isLoading ? <ActivityIndicator color="#111827" /> : null}
          </View>
          {accountQuery.isLoading ? null : sentItems.length === 0 ? (
            <Text style={styles.emptyText}>Aun no hay productos enviados a cocina.</Text>
          ) : (
            sentItems.map((item) => (
              <View key={`${item.pedido_id}-${item.id}`} style={styles.sentItem}>
                <Image source={item.imagen ? { uri: item.imagen } : PLACEHOLDER_FOOD} style={styles.sentImage} contentFit="cover" />
                <View style={styles.lineCopy}>
                  <Text style={styles.lineName} numberOfLines={2}>{item.cantidad}x {item.nombre}</Text>
                  <Text style={styles.sentMeta} numberOfLines={1}>{item.pedido_folio ?? 'Comanda'} - {item.estado ?? 'pendiente'}</Text>
                  {getPersistedModifierLabels(item.modificadores as unknown[]).map((label, index) => (
                    <Text key={`${item.id}-modifier-${index}`} style={styles.extraText} numberOfLines={1}>{label}</Text>
                  ))}
                </View>
                <Text style={styles.linePrice}>{formatMoney(item.subtotal)}</Text>
              </View>
            ))
          )}
        </View>
      </ScrollView>

      <SplitAccountModal
        visible={splitVisible}
        tableId={tableId}
        restaurantId={restaurantId}
        tableLabel={tableLabel}
        items={sentItems}
        activeSplit={activeSplit}
        onClose={() => setSplitVisible(false)}
        onSplitChanged={() => { void accountQuery.refetch(); }}
        onFullyPaid={finishSplitPayment}
      />

      <Modal visible={menuVisible} animationType="slide" onRequestClose={() => setMenuVisible(false)}>
        <SafeAreaView style={styles.modalSafe}>
          <View style={styles.menuHeader}>
            <TouchableOpacity style={styles.iconButton} onPress={() => setMenuVisible(false)} activeOpacity={0.8}>
              <Ionicons name="close" size={22} color="#111827" />
            </TouchableOpacity>
            <View style={styles.headerCopy}>
              <Text style={styles.title}>Menu</Text>
              <Text style={styles.subtitle}>{tableLabel} - {cartItems.length} pendientes</Text>
            </View>
            <TouchableOpacity style={styles.iconButton} onPress={() => dishesQuery.refetch()} activeOpacity={0.8}>
              {dishesQuery.isFetching ? <ActivityIndicator size="small" color="#111827" /> : <Ionicons name="refresh" size={20} color="#111827" />}
            </TouchableOpacity>
          </View>

          <View style={styles.menuSearchWrap}>
            <Ionicons name="search-outline" size={18} color="#64748B" />
            <TextInput
              value={menuSearch}
              onChangeText={setMenuSearch}
              placeholder="Buscar platillo, categoria o descripcion"
              placeholderTextColor="#94A3B8"
              style={styles.menuSearchInput}
              autoCapitalize="none"
              autoCorrect={false}
              returnKeyType="search"
            />
            {menuSearch ? (
              <TouchableOpacity
                accessibilityLabel="Limpiar busqueda"
                style={styles.menuSearchClear}
                onPress={() => setMenuSearch('')}
                activeOpacity={0.75}
              >
                <Ionicons name="close" size={16} color="#64748B" />
              </TouchableOpacity>
            ) : null}
          </View>

          <View style={styles.menuShell}>
            <View style={styles.categorySidebar}>
              <ScrollView contentContainerStyle={styles.categoryList} showsVerticalScrollIndicator={false}>
                {menuCategories.map((category) => {
                  const active = menuSearch.trim() ? category.id === 0 : (selectedCategoryId ?? 0) === category.id;
                  return (
                    <TouchableOpacity
                      key={category.id}
                      style={[styles.categoryTab, active && styles.categoryTabActive]}
                      activeOpacity={0.88}
                      onPress={() => {
                        setMenuSearch('');
                        setSelectedCategoryId(category.id === 0 ? null : category.id);
                      }}
                    >
                      <View style={[styles.categoryIconWrap, active && styles.categoryIconWrapActive]}>
                        <Ionicons name={categoryIcon(category.nombre)} size={18} color={active ? '#FFFFFF' : '#475569'} />
                      </View>
                      <Text style={[styles.categoryText, active && styles.categoryTextActive]} numberOfLines={2}>
                        {category.nombre}
                      </Text>
                      {typeof category.total_platillos === 'number' ? (
                        <Text style={[styles.categoryCount, active && styles.categoryCountActive]}>{category.total_platillos}</Text>
                      ) : null}
                    </TouchableOpacity>
                  );
                })}
              </ScrollView>
            </View>

            <View style={styles.productPane}>
              {dishesQuery.isLoading ? (
                <View style={styles.loadingProducts}>
                  <ActivityIndicator color="#111827" />
                  <Text style={styles.loadingText}>Cargando productos...</Text>
                </View>
              ) : (
                <FlatList
                  data={dishesQuery.data ?? []}
                  keyExtractor={(dish) => String(dish.id)}
                  contentContainerStyle={[styles.productList, cartItems.length > 0 && styles.productListWithCart]}
                  ListEmptyComponent={
                    <View style={styles.menuEmpty}>
                      <Ionicons name="restaurant-outline" size={34} color="#94A3B8" />
                      <Text style={styles.menuEmptyTitle}>{menuSearch.trim() ? 'Sin resultados' : 'Sin productos'}</Text>
                      <Text style={styles.menuEmptyText}>
                        {menuSearch.trim()
                          ? 'Intenta con otro nombre, ingrediente o categoria.'
                          : 'Esta categoria no tiene platillos disponibles.'}
                      </Text>
                    </View>
                  }
                  renderItem={({ item }) => (
                    <TouchableOpacity
                      style={[styles.productRow, !item.disponible && styles.productRowDisabled]}
                      activeOpacity={0.88}
                      onPress={() => openProduct(item)}
                      disabled={!item.disponible}
                    >
                      <Image source={item.imagen ? { uri: item.imagen } : PLACEHOLDER_FOOD} style={styles.productImage} contentFit="cover" />
                      <View style={styles.productCopy}>
                        <View style={styles.productTitleRow}>
                          <Text style={styles.productName} numberOfLines={2}>{item.nombre}</Text>
                          {!item.disponible ? <Text style={styles.unavailablePill}>Agotado</Text> : null}
                        </View>
                        <Text style={styles.productDescription} numberOfLines={2}>{item.descripcion ?? 'Sin descripcion'}</Text>
                        <View style={styles.productMetaRow}>
                          {item.tiempo_preparacion_min ? (
                            <Text style={styles.productMeta}>{item.tiempo_preparacion_min} min</Text>
                          ) : null}
                          {item.categoria_nombre ? <Text style={styles.productMeta} numberOfLines={1}>{item.categoria_nombre}</Text> : null}
                        </View>
                      </View>
                      <View style={styles.priceRail}>
                        <Text style={styles.productPrice}>{formatMoney(item.precio)}</Text>
                        <Ionicons name="add-circle" size={24} color={item.disponible ? '#2563EB' : '#CBD5E1'} />
                      </View>
                    </TouchableOpacity>
                  )}
                />
              )}
            </View>
          </View>

          {cartItems.length > 0 ? (
            <View style={[styles.menuCartBar, { bottom: 12 + Math.max(insets.bottom, Platform.OS === 'android' ? 8 : 0) }]}>
              <View>
                <Text style={styles.menuCartLabel}>{cartItems.length} productos pendientes</Text>
                <Text style={styles.menuCartTotal}>{formatMoney(cartTotal)}</Text>
              </View>
              <TouchableOpacity style={styles.menuSendButton} onPress={sendOrder} disabled={sending} activeOpacity={0.88}>
                {sending ? <ActivityIndicator color="#FFFFFF" /> : <Text style={styles.menuSendText}>Enviar</Text>}
              </TouchableOpacity>
            </View>
          ) : null}

          {Boolean(selectedProduct) || productLoading ? (
            <Pressable style={styles.productOverlay} onPress={() => !productLoading && setSelectedProduct(null)}>
              <Pressable
                style={[
                  styles.productSheet,
                  { paddingBottom: 14 + Math.max(insets.bottom, Platform.OS === 'android' ? 8 : 0) },
                ]}
                onPress={(event) => event.stopPropagation()}
              >
                <View style={styles.sheetHandle} />
                {productLoading || !selectedProduct ? (
                  <View style={styles.sheetLoading}>
                    <ActivityIndicator color="#111827" />
                    <Text style={styles.loadingText}>Abriendo producto...</Text>
                  </View>
                ) : (
                  <>
                    <View style={styles.sheetHeader}>
                      <Image source={selectedProduct.imagen ? { uri: selectedProduct.imagen } : PLACEHOLDER_FOOD} style={styles.sheetImage} contentFit="cover" />
                      <View style={styles.sheetTitleCopy}>
                        <Text style={styles.productModalTitle} numberOfLines={2}>{selectedProduct.nombre}</Text>
                        <Text style={styles.productModalPrice}>Base {formatMoney(selectedProduct.precio)}</Text>
                      </View>
                      <TouchableOpacity style={styles.sheetCloseButton} onPress={() => setSelectedProduct(null)}>
                        <Ionicons name="close" size={20} color="#111827" />
                      </TouchableOpacity>
                    </View>

                    <ScrollView style={styles.modifierScroll} contentContainerStyle={styles.modifierContent} showsVerticalScrollIndicator={false}>
                      {(selectedProduct.modificadores ?? []).length === 0 ? (
                        <Text style={styles.noModsText}>Sin modificadores. Puedes agregar notas para cocina.</Text>
                      ) : null}
                      {(selectedProduct.modificadores ?? []).map((mod) => (
                        <View key={mod.id} style={styles.modifierBlock}>
                          <View style={styles.modifierHeader}>
                            <Text style={styles.modifierTitle}>{mod.nombre}</Text>
                            <Text style={styles.modifierHint}>{mod.tipo === 'radio' ? 'Elige una' : 'Opcional'}</Text>
                          </View>
                          {mod.opciones.map((option) => {
                            const selected = isOptionSelected(mod.id, option.id);
                            const selectedOption = selectedMods
                              .find((group) => group.modificador_id === mod.id)
                              ?.opciones.find((item) => item.opcion_id === option.id);
                            const optionQuantity = Number(selectedOption?.cantidad ?? 1);
                            const maxQuantity = Math.max(1, Number(option.max_cantidad ?? 1));
                            return (
                              <TouchableOpacity
                                key={option.id}
                                style={[styles.optionRow, selected && styles.optionRowActive]}
                                activeOpacity={0.85}
                                onPress={() => toggleOption(mod, option)}
                              >
                                <View style={[styles.checkbox, mod.tipo === 'radio' && styles.radioBox, selected && styles.checkboxActive]}>
                                  {selected ? <Ionicons name="checkmark" size={15} color="#FFFFFF" /> : null}
                                </View>
                                <Text style={styles.optionName} numberOfLines={2}>{option.nombre}</Text>
                                {selected && maxQuantity > 1 ? (
                                  <View style={styles.optionQuantity}>
                                    <TouchableOpacity onPress={(event) => { event.stopPropagation(); changeOptionQuantity(mod.id, option.id, -1, maxQuantity); }}>
                                      <Ionicons name="remove-circle-outline" size={22} color="#334155" />
                                    </TouchableOpacity>
                                    <Text style={styles.optionQuantityText}>{optionQuantity}</Text>
                                    <TouchableOpacity
                                      disabled={optionQuantity >= maxQuantity}
                                      onPress={(event) => { event.stopPropagation(); changeOptionQuantity(mod.id, option.id, 1, maxQuantity); }}
                                    >
                                      <Ionicons name="add-circle-outline" size={22} color={optionQuantity >= maxQuantity ? '#CBD5E1' : '#334155'} />
                                    </TouchableOpacity>
                                  </View>
                                ) : null}
                                <Text style={styles.optionPrice}>+{formatMoney(option.precio_extra)}</Text>
                              </TouchableOpacity>
                            );
                          })}
                        </View>
                      ))}

                      <TextInput
                        value={notes}
                        onChangeText={setNotes}
                        placeholder="Notas para cocina"
                        placeholderTextColor="#94A3B8"
                        style={styles.notesInput}
                        multiline
                      />
                    </ScrollView>

                    <View style={styles.productFooter}>
                      <View style={styles.quantityPill}>
                        <TouchableOpacity onPress={() => setQuantity((current) => Math.max(1, current - 1))} style={styles.sheetQtyButton}>
                          <Ionicons name="remove" size={16} color="#111827" />
                        </TouchableOpacity>
                        <Text style={styles.quantityText}>{quantity}</Text>
                        <TouchableOpacity onPress={() => setQuantity((current) => current + 1)} style={styles.sheetQtyButton}>
                          <Ionicons name="add" size={16} color="#111827" />
                        </TouchableOpacity>
                      </View>
                      <TouchableOpacity style={styles.addToCartButton} onPress={addSelectedProduct} activeOpacity={0.88}>
                        <Text style={styles.addToCartText}>Agregar - {formatMoney(productUnitPrice * quantity)}</Text>
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
          <Pressable
            style={[
              styles.closeModal,
              { paddingBottom: 22 + Math.max(insets.bottom, Platform.OS === 'android' ? 8 : 0) },
            ]}
            onPress={(event) => event.stopPropagation()}
          >
            <View style={styles.sheetHandle} />
            <Text style={styles.closeTitle}>Cerrar cuenta</Text>
            <Text style={styles.closeText}>Selecciona el metodo de pago para liberar {tableLabel}.</Text>
            <Text style={styles.closeTotal}>{formatMoney(sentTotal)}</Text>

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
                  <View style={[styles.paymentIcon, selected && styles.paymentIconActive]}>
                    <Ionicons name={paymentIcon(value)} size={20} color={selected ? '#FFFFFF' : '#475569'} />
                  </View>
                  <Text style={styles.paymentOptionText}>{label}</Text>
                  <Ionicons name={selected ? 'checkmark-circle' : 'ellipse-outline'} size={22} color={selected ? '#2563EB' : '#CBD5E1'} />
                </TouchableOpacity>
              );
            })}

            <View style={styles.closeActions}>
              <TouchableOpacity style={styles.cancelCloseButton} onPress={() => setCloseVisible(false)} disabled={closing}>
                <Text style={styles.cancelCloseText}>Cancelar</Text>
              </TouchableOpacity>
              <TouchableOpacity style={styles.confirmCloseButton} onPress={closeAccount} disabled={closing}>
                {closing ? <ActivityIndicator color="#FFFFFF" /> : <Text style={styles.confirmCloseText}>Cerrar cuenta</Text>}
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
    backgroundColor: '#F4F6F8',
  },
  modalSafe: {
    flex: 1,
    backgroundColor: '#F4F6F8',
  },
  header: {
    paddingHorizontal: 16,
    paddingVertical: 10,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    backgroundColor: '#F4F6F8',
  },
  menuHeader: {
    paddingHorizontal: 14,
    paddingVertical: 10,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    backgroundColor: '#FFFFFF',
    borderBottomWidth: 1,
    borderBottomColor: '#E5E7EB',
  },
  menuSearchWrap: {
    minHeight: 50,
    marginHorizontal: 10,
    marginVertical: 10,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: '#E2E8F0',
    backgroundColor: '#FFFFFF',
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 12,
    gap: 10,
  },
  menuSearchInput: {
    flex: 1,
    minHeight: 48,
    color: '#111827',
    fontSize: 14,
    fontWeight: '800',
  },
  menuSearchClear: {
    width: 28,
    height: 28,
    borderRadius: 14,
    backgroundColor: '#F1F5F9',
    alignItems: 'center',
    justifyContent: 'center',
  },
  iconButton: {
    width: 42,
    height: 42,
    borderRadius: 14,
    backgroundColor: '#FFFFFF',
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: '#E5E7EB',
  },
  headerCopy: {
    flex: 1,
    minWidth: 0,
  },
  title: {
    fontSize: 22,
    fontWeight: '900',
    color: '#111827',
  },
  subtitle: {
    marginTop: 2,
    fontSize: 13,
    color: '#64748B',
    fontWeight: '800',
  },
  content: {
    paddingHorizontal: 14,
    paddingBottom: 28,
    gap: 14,
  },
  accountPanel: {
    borderRadius: 22,
    backgroundColor: '#111827',
    padding: 16,
  },
  accountPanelTop: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 10,
  },
  accountBadge: {
    minHeight: 32,
    borderRadius: 999,
    backgroundColor: 'rgba(255,255,255,0.12)',
    paddingHorizontal: 10,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  accountBadgeText: {
    color: '#FFFFFF',
    fontSize: 12,
    fontWeight: '900',
  },
  accountMetaText: {
    color: '#CBD5E1',
    fontSize: 12,
    fontWeight: '800',
  },
  totalText: {
    marginTop: 12,
    fontSize: 38,
    fontWeight: '900',
    color: '#FFFFFF',
  },
  accountStats: {
    marginTop: 14,
    flexDirection: 'row',
    gap: 8,
  },
  statBox: {
    flex: 1,
    minHeight: 58,
    borderRadius: 16,
    backgroundColor: 'rgba(255,255,255,0.10)',
    paddingHorizontal: 10,
    justifyContent: 'center',
  },
  statValue: {
    color: '#FFFFFF',
    fontWeight: '900',
    fontSize: 16,
  },
  statLabel: {
    marginTop: 3,
    color: '#CBD5E1',
    fontSize: 11,
    fontWeight: '800',
  },
  accountPeople: {
    marginTop: 12,
    gap: 4,
  },
  personLine: {
    color: '#E5E7EB',
    fontSize: 13,
    fontWeight: '800',
  },
  actionGrid: {
    flexDirection: 'row',
    gap: 10,
  },
  primaryActionButton: {
    flex: 1.2,
    minHeight: 56,
    borderRadius: 18,
    backgroundColor: '#2563EB',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
  },
  primaryActionText: {
    fontSize: 15,
    fontWeight: '900',
    color: '#FFFFFF',
  },
  secondaryActionButton: {
    flex: 1,
    minHeight: 56,
    borderRadius: 18,
    backgroundColor: '#FFFFFF',
    borderWidth: 1,
    borderColor: '#D1D5DB',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
  },
  secondaryActionText: {
    fontSize: 15,
    fontWeight: '900',
    color: '#111827',
  },
  actionButtonDisabled: {
    opacity: 0.45,
  },
  section: {
    borderRadius: 20,
    backgroundColor: '#FFFFFF',
    padding: 14,
    borderWidth: 1,
    borderColor: '#E5E7EB',
  },
  pendingSection: {
    borderColor: '#BFDBFE',
  },
  sectionHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 12,
    marginBottom: 10,
  },
  sectionTitle: {
    fontSize: 17,
    fontWeight: '900',
    color: '#111827',
  },
  sectionSubtitle: {
    marginTop: 2,
    fontSize: 12,
    color: '#64748B',
    fontWeight: '800',
  },
  clearButton: {
    minHeight: 34,
    borderRadius: 12,
    paddingHorizontal: 10,
    backgroundColor: '#FEF2F2',
    flexDirection: 'row',
    alignItems: 'center',
    gap: 5,
  },
  clearText: {
    color: '#B91C1C',
    fontWeight: '900',
    fontSize: 12,
  },
  pendingItem: {
    paddingVertical: 12,
    borderTopWidth: 1,
    borderTopColor: '#F1F5F9',
    flexDirection: 'row',
    gap: 10,
  },
  itemQtyBadge: {
    width: 34,
    height: 34,
    borderRadius: 12,
    backgroundColor: '#EFF6FF',
    alignItems: 'center',
    justifyContent: 'center',
  },
  itemQtyText: {
    fontSize: 14,
    fontWeight: '900',
    color: '#2563EB',
  },
  sentItem: {
    paddingVertical: 12,
    borderTopWidth: 1,
    borderTopColor: '#F1F5F9',
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  sentImage: {
    width: 46,
    height: 46,
    borderRadius: 14,
    backgroundColor: '#F1F5F9',
  },
  lineCopy: {
    flex: 1,
    minWidth: 0,
  },
  lineName: {
    fontSize: 14,
    fontWeight: '900',
    color: '#111827',
  },
  extraText: {
    marginTop: 3,
    fontSize: 12,
    color: '#64748B',
    fontWeight: '700',
  },
  noteText: {
    marginTop: 4,
    fontSize: 12,
    color: '#92400E',
    fontWeight: '800',
  },
  sentMeta: {
    marginTop: 4,
    fontSize: 12,
    color: '#64748B',
    fontWeight: '700',
  },
  lineActions: {
    alignItems: 'flex-end',
    gap: 8,
  },
  linePrice: {
    fontSize: 14,
    fontWeight: '900',
    color: '#111827',
  },
  qtyRow: {
    flexDirection: 'row',
    gap: 6,
  },
  qtyButton: {
    width: 31,
    height: 31,
    borderRadius: 12,
    backgroundColor: '#F1F5F9',
    alignItems: 'center',
    justifyContent: 'center',
  },
  sendButton: {
    marginTop: 14,
    minHeight: 52,
    borderRadius: 16,
    backgroundColor: '#111827',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
  },
  sendButtonText: {
    color: '#FFFFFF',
    fontWeight: '900',
    fontSize: 15,
  },
  emptyText: {
    paddingTop: 4,
    color: '#64748B',
    fontWeight: '800',
    lineHeight: 20,
  },
  menuShell: {
    flex: 1,
    flexDirection: 'row',
  },
  categorySidebar: {
    width: 108,
    backgroundColor: '#FFFFFF',
    borderRightWidth: 1,
    borderRightColor: '#E5E7EB',
  },
  categoryList: {
    padding: 8,
    gap: 8,
    paddingBottom: 100,
  },
  categoryTab: {
    minHeight: 86,
    borderRadius: 16,
    padding: 8,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 6,
  },
  categoryTabActive: {
    backgroundColor: '#111827',
  },
  categoryIconWrap: {
    width: 34,
    height: 34,
    borderRadius: 12,
    backgroundColor: '#F1F5F9',
    alignItems: 'center',
    justifyContent: 'center',
  },
  categoryIconWrapActive: {
    backgroundColor: 'rgba(255,255,255,0.16)',
  },
  categoryText: {
    textAlign: 'center',
    color: '#475569',
    fontWeight: '900',
    fontSize: 11,
    lineHeight: 14,
  },
  categoryTextActive: {
    color: '#FFFFFF',
  },
  categoryCount: {
    color: '#94A3B8',
    fontSize: 11,
    fontWeight: '900',
  },
  categoryCountActive: {
    color: '#CBD5E1',
  },
  productPane: {
    flex: 1,
  },
  productList: {
    padding: 10,
    gap: 10,
    paddingBottom: 24,
  },
  productListWithCart: {
    paddingBottom: 108,
  },
  productRow: {
    minHeight: 116,
    borderRadius: 18,
    backgroundColor: '#FFFFFF',
    padding: 10,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    borderWidth: 1,
    borderColor: '#E5E7EB',
  },
  productRowDisabled: {
    opacity: 0.56,
  },
  productImage: {
    width: 70,
    height: 82,
    borderRadius: 14,
    backgroundColor: '#F1F5F9',
  },
  productCopy: {
    flex: 1,
    minWidth: 0,
  },
  productTitleRow: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 6,
  },
  productName: {
    flex: 1,
    fontSize: 14,
    fontWeight: '900',
    color: '#111827',
  },
  unavailablePill: {
    borderRadius: 999,
    paddingHorizontal: 6,
    paddingVertical: 3,
    backgroundColor: '#FEE2E2',
    color: '#B91C1C',
    fontSize: 10,
    fontWeight: '900',
  },
  productDescription: {
    marginTop: 5,
    fontSize: 12,
    color: '#64748B',
    lineHeight: 16,
    fontWeight: '700',
  },
  productMetaRow: {
    marginTop: 8,
    flexDirection: 'row',
    gap: 6,
  },
  productMeta: {
    maxWidth: 90,
    borderRadius: 999,
    paddingHorizontal: 7,
    paddingVertical: 3,
    backgroundColor: '#F1F5F9',
    color: '#475569',
    fontSize: 10,
    fontWeight: '900',
  },
  priceRail: {
    alignSelf: 'stretch',
    alignItems: 'flex-end',
    justifyContent: 'space-between',
  },
  productPrice: {
    fontSize: 14,
    fontWeight: '900',
    color: '#111827',
  },
  loadingProducts: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 10,
  },
  loadingText: {
    color: '#64748B',
    fontWeight: '800',
  },
  menuEmpty: {
    minHeight: 260,
    alignItems: 'center',
    justifyContent: 'center',
    padding: 18,
  },
  menuEmptyTitle: {
    marginTop: 10,
    fontSize: 16,
    fontWeight: '900',
    color: '#111827',
  },
  menuEmptyText: {
    marginTop: 4,
    textAlign: 'center',
    color: '#64748B',
    fontWeight: '700',
  },
  menuCartBar: {
    position: 'absolute',
    left: 118,
    right: 10,
    bottom: 12,
    minHeight: 70,
    borderRadius: 20,
    backgroundColor: '#111827',
    paddingHorizontal: 14,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 12,
  },
  menuCartLabel: {
    color: '#CBD5E1',
    fontSize: 12,
    fontWeight: '800',
  },
  menuCartTotal: {
    marginTop: 2,
    color: '#FFFFFF',
    fontSize: 20,
    fontWeight: '900',
  },
  menuSendButton: {
    minWidth: 92,
    minHeight: 44,
    borderRadius: 14,
    backgroundColor: '#2563EB',
    alignItems: 'center',
    justifyContent: 'center',
  },
  menuSendText: {
    color: '#FFFFFF',
    fontWeight: '900',
  },
  productOverlay: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: 'rgba(15, 23, 42, 0.50)',
    justifyContent: 'flex-end',
  },
  productSheet: {
    maxHeight: '88%',
    borderTopLeftRadius: 26,
    borderTopRightRadius: 26,
    backgroundColor: '#FFFFFF',
    paddingHorizontal: 16,
    paddingTop: 10,
    paddingBottom: 14,
  },
  sheetHandle: {
    alignSelf: 'center',
    width: 42,
    height: 5,
    borderRadius: 999,
    backgroundColor: '#CBD5E1',
    marginBottom: 12,
  },
  sheetLoading: {
    minHeight: 180,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 10,
  },
  sheetHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  sheetImage: {
    width: 62,
    height: 62,
    borderRadius: 16,
    backgroundColor: '#F1F5F9',
  },
  sheetTitleCopy: {
    flex: 1,
    minWidth: 0,
  },
  sheetCloseButton: {
    width: 38,
    height: 38,
    borderRadius: 13,
    backgroundColor: '#F1F5F9',
    alignItems: 'center',
    justifyContent: 'center',
  },
  productModalTitle: {
    fontSize: 20,
    fontWeight: '900',
    color: '#111827',
  },
  productModalPrice: {
    marginTop: 4,
    color: '#64748B',
    fontWeight: '900',
  },
  modifierScroll: {
    marginTop: 14,
  },
  modifierContent: {
    paddingBottom: 10,
  },
  noModsText: {
    marginBottom: 12,
    color: '#64748B',
    fontWeight: '800',
  },
  modifierBlock: {
    marginBottom: 14,
  },
  modifierHeader: {
    marginBottom: 8,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  modifierTitle: {
    fontSize: 15,
    fontWeight: '900',
    color: '#111827',
  },
  modifierHint: {
    color: '#64748B',
    fontSize: 12,
    fontWeight: '900',
  },
  optionRow: {
    minHeight: 50,
    borderRadius: 15,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    paddingHorizontal: 10,
    marginBottom: 8,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  optionRowActive: {
    borderColor: '#2563EB',
    backgroundColor: '#EFF6FF',
  },
  checkbox: {
    width: 25,
    height: 25,
    borderRadius: 8,
    borderWidth: 1.4,
    borderColor: '#CBD5E1',
    alignItems: 'center',
    justifyContent: 'center',
  },
  radioBox: {
    borderRadius: 13,
  },
  checkboxActive: {
    backgroundColor: '#2563EB',
    borderColor: '#2563EB',
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
    color: '#475569',
  },
  optionQuantity: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
  },
  optionQuantityText: {
    minWidth: 18,
    textAlign: 'center',
    fontSize: 13,
    fontWeight: '900',
    color: '#111827',
  },
  notesInput: {
    minHeight: 82,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: '#CBD5E1',
    paddingHorizontal: 12,
    paddingVertical: 10,
    color: '#111827',
    fontWeight: '800',
    backgroundColor: '#F8FAFC',
  },
  productFooter: {
    marginTop: 12,
    flexDirection: 'row',
    gap: 10,
  },
  quantityPill: {
    flexDirection: 'row',
    alignItems: 'center',
    borderRadius: 18,
    backgroundColor: '#F1F5F9',
    padding: 5,
  },
  sheetQtyButton: {
    width: 34,
    height: 34,
    borderRadius: 13,
    backgroundColor: '#FFFFFF',
    alignItems: 'center',
    justifyContent: 'center',
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
    minHeight: 48,
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
    backgroundColor: 'rgba(15, 23, 42, 0.50)',
    justifyContent: 'flex-end',
  },
  closeModal: {
    borderTopLeftRadius: 26,
    borderTopRightRadius: 26,
    backgroundColor: '#FFFFFF',
    paddingHorizontal: 18,
    paddingTop: 10,
    paddingBottom: 22,
  },
  closeTitle: {
    fontSize: 22,
    fontWeight: '900',
    color: '#111827',
  },
  closeText: {
    marginTop: 6,
    color: '#64748B',
    fontWeight: '800',
    lineHeight: 20,
  },
  closeTotal: {
    marginTop: 14,
    marginBottom: 14,
    fontSize: 34,
    fontWeight: '900',
    color: '#111827',
  },
  paymentOption: {
    minHeight: 58,
    borderRadius: 18,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    paddingHorizontal: 12,
    marginBottom: 10,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  paymentOptionActive: {
    borderColor: '#BFDBFE',
    backgroundColor: '#EFF6FF',
  },
  paymentIcon: {
    width: 38,
    height: 38,
    borderRadius: 13,
    backgroundColor: '#F1F5F9',
    alignItems: 'center',
    justifyContent: 'center',
  },
  paymentIconActive: {
    backgroundColor: '#2563EB',
  },
  paymentOptionText: {
    flex: 1,
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
    minHeight: 52,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: '#CBD5E1',
    alignItems: 'center',
    justifyContent: 'center',
  },
  cancelCloseText: {
    fontWeight: '900',
    color: '#111827',
  },
  confirmCloseButton: {
    flex: 1.25,
    minHeight: 52,
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
