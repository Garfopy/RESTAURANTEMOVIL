import React, { useEffect, useMemo, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  FlatList,
  Keyboard,
  KeyboardAvoidingView,
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
import * as Haptics from 'expo-haptics';
import type { Modificador, ModificadorSeleccionado, OpcionModificador, Platillo } from '@amare/types';
import { getCategories, getDishById, getDishes } from '../../../services/menu.service';
import { getApiError } from '../../../services/api';
import {
  EMPTY_FISCAL_DATA,
  buildInvoiceRequest,
  getFiscalData,
  validateFiscalData,
  type FiscalData,
} from '../../../services/fiscal.service';
import {
  closeWaiterAccount,
  createWaiterOrder,
  getWaiterAccount,
  type WaiterAccountItem,
  type WaiterPaymentMethod,
  type WaiterSplitAccount,
} from '../../../services/waiter.service';
import { useWaiterCartStore } from '../../../store/waiter-cart.store';
import { useBranchConfigStore } from '../../../store/branch.store';
import { SplitAccountModal } from '../../../components/waiter/SplitAccountModal';
import {
  WaiterTicketPreviewModal,
  type WaiterTicketLine,
  type WaiterTicketStatus,
} from '../../../components/waiter/WaiterTicketPreviewModal';
import { InvoiceRequestForm } from '../../../components/shared/InvoiceRequestForm';

const PLACEHOLDER_FOOD = require('../../../assets/placeholder-food.jpg');

type IconName = keyof typeof Ionicons.glyphMap;
type TicketCloseAction = 'none' | 'reopenSplit' | 'finishSplit' | 'goBack';
type SplitTicketPreview = {
  account: WaiterSplitAccount;
  status: WaiterTicketStatus;
  method?: WaiterPaymentMethod | null;
  finishAfterClose?: boolean;
};
type TipMode = 'none' | '10' | '15' | '20' | 'custom';
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

function defaultModifierSelection(platillo: Platillo): ModificadorSeleccionado[] {
  if (!platillo.selector) return [];
  const result: ModificadorSeleccionado[] = [];
  const omitted = platillo.selector.incluidas.filter(
    (item) => item.omitida_por_defecto || !item.seleccionada_por_defecto
  );
  if (omitted.length > 0) {
    result.push({
      modificador_id: -2,
      modificador_nombre: 'Incluidos',
      opciones: omitted.map((item) => ({
        opcion_id: item.id, opcion_nombre: item.nombre, precio_extra: 0,
        cantidad: 1, tipo_modificador: 'exclusion',
      })),
    });
  }
  const extras = platillo.selector.extras.filter((item) => item.cantidad_inicial > 0);
  if (extras.length > 0) {
    result.push({
      modificador_id: -1,
      modificador_nombre: 'Extras',
      opciones: extras.map((item) => ({
        opcion_id: item.id, opcion_nombre: item.nombre, precio_extra: item.precio_unitario,
        cantidad: item.cantidad_inicial, tipo_modificador: 'extra',
      })),
    });
  }
  return result;
}

function getPersistedModifierLabels(modifiers: unknown): string[] {
  const list = Array.isArray(modifiers) ? modifiers : [];
  return list.flatMap((modifier: any) => {
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

function buildAccountTicketLines(items: WaiterAccountItem[]): WaiterTicketLine[] {
  return items.map((item) => ({
    key: `${item.pedido_id}-${item.id}`,
    name: item.nombre,
    quantity: Number(item.cantidad || 0),
    unitPrice: Number(item.precio_unit || 0),
    subtotal: Number(item.subtotal || 0),
    notes: item.notas ?? null,
    modifiers: getPersistedModifierLabels(item.modificadores),
  }));
}

function buildSplitTicketLines(account: WaiterSplitAccount, items: WaiterAccountItem[]): WaiterTicketLine[] {
  const itemsById = new Map(items.map((item) => [item.id, item]));
  return account.items.map((item) => {
    const source = itemsById.get(item.pedido_item_id);
    return {
      key: `${account.id}-${item.pedido_item_id}`,
      name: source?.nombre ?? 'Producto',
      quantity: Number(item.cantidad || 0),
      unitPrice: Number(item.precio_unit || 0),
      subtotal: Number(item.subtotal || 0),
      notes: source?.notas ?? null,
      modifiers: getPersistedModifierLabels(source?.modificadores),
    };
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
  const [pendingReviewVisible, setPendingReviewVisible] = useState(false);
  const [ticketVisible, setTicketVisible] = useState(false);
  const [ticketStatus, setTicketStatus] = useState<WaiterTicketStatus>('prebill');
  const [ticketPaymentMethod, setTicketPaymentMethod] = useState<WaiterPaymentMethod | null>(null);
  const [ticketAccountName, setTicketAccountName] = useState<string | null>(null);
  const [ticketLines, setTicketLines] = useState<WaiterTicketLine[]>([]);
  const [ticketCloseAction, setTicketCloseAction] = useState<TicketCloseAction>('none');
  const [ticketTipAmount, setTicketTipAmount] = useState(0);
  const [paymentMethod, setPaymentMethod] = useState<WaiterPaymentMethod>('efectivo');
  const [tipMode, setTipMode] = useState<TipMode>('none');
  const [customTip, setCustomTip] = useState('');
  const [invoiceRequired, setInvoiceRequired] = useState(false);
  const [invoiceSaveToProfile, setInvoiceSaveToProfile] = useState(true);
  const [invoiceFiscalData, setInvoiceFiscalData] = useState<FiscalData>(EMPTY_FISCAL_DATA);
  const [selectedCategoryId, setSelectedCategoryId] = useState<number | null>(null);
  const [menuSearch, setMenuSearch] = useState('');
  const [selectedProduct, setSelectedProduct] = useState<Platillo | null>(null);
  const [productLoading, setProductLoading] = useState(false);
  const [loadingProductId, setLoadingProductId] = useState<number | null>(null);
  const [quantity, setQuantity] = useState(1);
  const [notes, setNotes] = useState('');
  const [selectedMods, setSelectedMods] = useState<ModificadorSeleccionado[]>([]);
  const [sending, setSending] = useState(false);
  const [closing, setClosing] = useState(false);
  const [keyboardVisible, setKeyboardVisible] = useState(false);
  const resumedSplitId = useRef<number | null>(null);
  const configVersion = useBranchConfigStore((state) => state.branchId === restaurantId ? state.version : 0);
  const config = useBranchConfigStore((state) => state.branchId === restaurantId ? state.config : null);
  const refreshBranchConfig = useBranchConfigStore((state) => state.refresh);
  const invoiceEnabled = Boolean(config?.facturacion?.habilitada);

  const waiterCart = useWaiterCartStore();
  const cartItems = waiterCart.tableId === tableId && waiterCart.restaurantId === restaurantId ? waiterCart.items : [];
  const cartTotal = waiterCart.tableId === tableId && waiterCart.restaurantId === restaurantId ? waiterCart.total : 0;

  const accountQuery = useQuery({
    queryKey: ['waiter', 'account', restaurantId, tableId],
    queryFn: () => getWaiterAccount(tableId, restaurantId),
    enabled: Number.isFinite(tableId) && Number.isFinite(restaurantId),
  });
  const categoriesQuery = useQuery({
    queryKey: ['waiter', 'categories', restaurantId, configVersion],
    queryFn: () => getCategories(restaurantId),
    enabled: Number.isFinite(restaurantId),
  });
  const dishesQuery = useQuery({
    queryKey: ['waiter', 'dishes', restaurantId, selectedCategoryId, menuSearch.trim(), configVersion],
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
  const selectedTipAmount = useMemo(() => {
    if (tipMode === 'custom') return Math.max(0, money(customTip));
    if (tipMode === 'none') return 0;
    return Math.round(sentTotal * Number(tipMode)) / 100;
  }, [customTip, sentTotal, tipMode]);
  const closeGrandTotal = sentTotal + selectedTipAmount;
  const activeSplit = account?.active_split ?? null;
  const totalDue = sentTotal + cartTotal;
  const hasPendingCart = cartItems.length > 0;
  const canCloseAccount = sentItems.length > 0 && !hasPendingCart;
  const sentGroups = useMemo(() => {
    const groups = new Map<string, { key: string; name: string; total: number; items: typeof sentItems }>();

    sentItems.forEach((item) => {
      const name = item.pedido_cliente_nombre?.trim() || customerName || 'Comensal';
      const key = item.pedido_mobile_usuario_id ? `user-${item.pedido_mobile_usuario_id}` : `name-${name}`;
      const group = groups.get(key) ?? { key, name, total: 0, items: [] };
      group.total += Number(item.subtotal || 0);
      group.items.push(item);
      groups.set(key, group);
    });

    return Array.from(groups.values());
  }, [customerName, sentItems]);

  const accountTicketLines = useMemo<WaiterTicketLine[]>(() => buildAccountTicketLines(sentItems), [sentItems]);

  useEffect(() => {
    if (!activeSplit || resumedSplitId.current === activeSplit.id) return;
    resumedSplitId.current = activeSplit.id;
    setSplitVisible(true);
  }, [activeSplit]);

  useEffect(() => {
    if (pendingReviewVisible && cartItems.length === 0) {
      setPendingReviewVisible(false);
    }
  }, [cartItems.length, pendingReviewVisible]);

  useEffect(() => {
    if (!invoiceEnabled) {
      setInvoiceRequired(false);
      return;
    }

    let cancelled = false;
    async function loadFiscalData() {
      const saved = await getFiscalData().catch(() => null);
      if (!cancelled && saved) setInvoiceFiscalData(saved);
    }

    void loadFiscalData();
    return () => {
      cancelled = true;
    };
  }, [invoiceEnabled]);

  useEffect(() => {
    const showEvent = Platform.OS === 'ios' ? 'keyboardWillShow' : 'keyboardDidShow';
    const hideEvent = Platform.OS === 'ios' ? 'keyboardWillHide' : 'keyboardDidHide';
    const show = Keyboard.addListener(showEvent, () => setKeyboardVisible(true));
    const hide = Keyboard.addListener(hideEvent, () => setKeyboardVisible(false));

    return () => {
      show.remove();
      hide.remove();
    };
  }, []);

  const menuCategories = useMemo<MenuCategory[]>(
    () => [{ id: 0, nombre: 'Todo' }, ...((categoriesQuery.data ?? []) as MenuCategory[])],
    [categoriesQuery.data]
  );

  const productUnitPrice = useMemo(
    () => (selectedProduct ? unitPrice(selectedProduct, selectedMods) : 0),
    [selectedProduct, selectedMods]
  );

  async function openProduct(product: Platillo, quickAdd = false) {
    try {
      setProductLoading(!quickAdd);
      setLoadingProductId(product.id);
      setSelectedProduct(null);
      setQuantity(1);
      setNotes('');
      setSelectedMods([]);
      const fullProduct = await getDishById(restaurantId, product.id);
      const defaultSelection = defaultModifierSelection(fullProduct);
      const hasChoices = (fullProduct.modificadores ?? []).length > 0 || defaultSelection.length > 0;

      if (quickAdd && !hasChoices) {
        waiterCart.addItem({
          tableId,
          restaurantId,
          clienteNombre: customerName,
          platillo: fullProduct,
          cantidad: 1,
          modificadores: [],
          notas: '',
        });
        void Haptics.selectionAsync();
        return;
      }

      setSelectedProduct(fullProduct);
      setSelectedMods(defaultSelection);
    } catch (error) {
      Alert.alert('Producto', getApiError(error));
    } finally {
      if (!quickAdd) setProductLoading(false);
      setLoadingProductId(null);
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

  async function openMenu() {
    setMenuVisible(true);
    await Promise.all([
      refreshBranchConfig(restaurantId),
      categoriesQuery.refetch(),
      dishesQuery.refetch(),
    ]).catch(() => undefined);
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

    Keyboard.dismiss();
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

  function requestClearCart() {
    if (cartItems.length === 0) return;
    Alert.alert('Vaciar comanda', 'Se quitarán los productos pendientes antes de enviarlos a cocina.', [
      { text: 'Conservar', style: 'cancel' },
      { text: 'Vaciar', style: 'destructive', onPress: () => {
        waiterCart.clear();
        setPendingReviewVisible(false);
      } },
    ]);
  }

  function requestSendOrder() {
    if (cartItems.length === 0) {
      Alert.alert('Comanda vacía', 'Agrega productos antes de enviar a cocina.');
      return;
    }
    setPendingReviewVisible(true);
  }

  async function sendOrder() {
    if (cartItems.length === 0) {
      Alert.alert('Comanda vacía', 'Agrega productos antes de enviar a cocina.');
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
      setPendingReviewVisible(false);
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
      Alert.alert('Comanda pendiente', 'Envía o vacía la comanda pendiente antes de cerrar la cuenta.');
      return;
    }
    if (sentItems.length === 0) {
      Alert.alert('Cuenta vacía', 'No hay productos enviados para cerrar esta cuenta.');
      return;
    }

    const invoiceValidation = invoiceRequired ? validateFiscalData(invoiceFiscalData) : null;
    if (invoiceValidation) {
      Alert.alert('Datos fiscales incompletos', invoiceValidation);
      return;
    }
    const invoiceRequest = buildInvoiceRequest(invoiceRequired, invoiceFiscalData, invoiceSaveToProfile);

    try {
      setClosing(true);
      await closeWaiterAccount({
        tableId,
        restaurantId,
        metodoPago: paymentMethod,
        propina: selectedTipAmount,
        invoiceRequest,
      });
      waiterCart.clear();
      setCloseVisible(false);
      setTicketStatus('paid');
      setTicketPaymentMethod(paymentMethod);
      setTicketAccountName(null);
      setTicketLines(accountTicketLines);
      setTicketTipAmount(selectedTipAmount);
      setTicketCloseAction('goBack');
      if (invoiceRequest) {
        Alert.alert('Solicitud de factura recibida', 'La solicitud quedo registrada para esta cuenta.');
      }
      showTicketAfterModalTransition();
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
    Alert.alert('Cerrar cuenta', '¿Cómo deseas cobrar esta mesa?', [
      { text: 'Cancelar', style: 'cancel' },
      { text: 'Una sola cuenta', onPress: () => setCloseVisible(true) },
      { text: 'Cuentas separadas', onPress: () => setSplitVisible(true) },
    ]);
  }

  function finishSplitPayment() {
    waiterCart.clear();
    setSplitVisible(false);
    Alert.alert('Mesa liquidada', 'Todas las cuentas fueron pagadas y la mesa quedó disponible.', [
      { text: 'Aceptar', onPress: () => router.replace('/(waiter)' as never) },
    ]);
  }

  function showTicketAfterModalTransition() {
    const delayMs = Platform.OS === 'ios' ? 360 : 80;
    setTimeout(() => setTicketVisible(true), delayMs);
  }

  function openAccountTicket(status: WaiterTicketStatus) {
    if (sentItems.length === 0) {
      Alert.alert('Ticket', 'No hay productos enviados para generar la precuenta.');
      return;
    }
    setTicketStatus(status);
    setTicketPaymentMethod(status === 'paid' ? paymentMethod : null);
    setTicketAccountName(null);
    setTicketLines(accountTicketLines);
    setTicketTipAmount(selectedTipAmount);
    setTicketCloseAction('none');
    setCloseVisible(false);
    showTicketAfterModalTransition();
  }

  function openSplitTicketPreview(preview: SplitTicketPreview) {
    const lines = buildSplitTicketLines(preview.account, sentItems);
    if (lines.length === 0) {
      Alert.alert('Ticket', 'No hay productos para generar la precuenta.');
      return;
    }

    setTicketStatus(preview.status);
    setTicketPaymentMethod(
      preview.status === 'paid'
        ? preview.method ?? preview.account.metodo_pago ?? paymentMethod
        : null
    );
    setTicketAccountName(preview.account.nombre ?? null);
    setTicketLines(lines);
    setTicketTipAmount(0);
    setTicketCloseAction(preview.finishAfterClose ? 'finishSplit' : 'reopenSplit');
    setSplitVisible(false);
    showTicketAfterModalTransition();
  }

  function closeTicketPreview() {
    const nextAction = ticketCloseAction;
    setTicketVisible(false);
    setTicketAccountName(null);
    setTicketPaymentMethod(null);
    setTicketTipAmount(0);
    setTicketCloseAction('none');

    if (nextAction === 'reopenSplit') {
      setSplitVisible(true);
      return;
    }
    if (nextAction === 'finishSplit') {
      finishSplitPayment();
      return;
    }
    if (nextAction === 'goBack') {
      router.replace('/(waiter)' as never);
    }
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

  function renderReviewItem(item: (typeof cartItems)[number]) {
    return (
      <View key={`review-${item.id}`} style={styles.reviewItem}>
        <Image source={item.platillo.imagen ? { uri: item.platillo.imagen } : PLACEHOLDER_FOOD} style={styles.reviewImage} contentFit="cover" />
        <View style={styles.reviewCopy}>
          <View style={styles.reviewTitleRow}>
            <Text style={styles.reviewName} numberOfLines={2}>{item.cantidad}x {item.platillo.nombre}</Text>
            <Text style={styles.reviewPrice}>{formatMoney(item.subtotal)}</Text>
          </View>
          {getSelectedExtras(item.modificadores).map((extra) => (
            <Text key={`review-extra-${item.id}-${extra.key}`} style={styles.reviewMeta} numberOfLines={1}>
              + {extra.nombre} {formatMoney(extra.precio)}
            </Text>
          ))}
          {item.notas ? (
            <View style={styles.reviewNoteRow}>
              <Ionicons name="chatbox-ellipses-outline" size={14} color="#92400E" />
              <Text style={styles.reviewNote} numberOfLines={3}>{item.notas}</Text>
            </View>
          ) : null}
        </View>
        <View style={styles.reviewQtyActions}>
          <TouchableOpacity onPress={() => waiterCart.updateQty(item.id, item.cantidad - 1)} style={styles.qtyButton}>
            <Ionicons name={item.cantidad > 1 ? 'remove' : 'trash-outline'} size={15} color="#111827" />
          </TouchableOpacity>
          <TouchableOpacity onPress={() => waiterCart.updateQty(item.id, item.cantidad + 1)} style={styles.qtyButton}>
            <Ionicons name="add" size={15} color="#111827" />
          </TouchableOpacity>
        </View>
      </View>
    );
  }

  function renderPendingReviewSheet(inline = false) {
    return (
      <Pressable
        style={[styles.reviewOverlay, inline && styles.reviewInlineOverlay]}
        onPress={() => setPendingReviewVisible(false)}
      >
        <Pressable
          style={[
            styles.reviewSheet,
            { paddingBottom: 16 + Math.max(insets.bottom, Platform.OS === 'android' ? 8 : 0) },
          ]}
          onPress={(event) => event.stopPropagation()}
        >
          <View style={styles.sheetHandle} />
          <View style={styles.reviewHeader}>
            <View style={styles.reviewIcon}>
              <Ionicons name="receipt-outline" size={20} color="#FFFFFF" />
            </View>
            <View style={styles.reviewHeaderCopy}>
              <Text style={styles.reviewTitle}>Revisar comanda</Text>
              <Text style={styles.reviewSubtitle}>{tableLabel} - {cartItems.length} productos pendientes</Text>
            </View>
            <TouchableOpacity style={styles.sheetCloseButton} onPress={() => setPendingReviewVisible(false)}>
              <Ionicons name="close" size={20} color="#111827" />
            </TouchableOpacity>
          </View>

          <ScrollView
            style={styles.reviewList}
            contentContainerStyle={styles.reviewListContent}
            showsVerticalScrollIndicator={false}
          >
            {cartItems.map(renderReviewItem)}
          </ScrollView>

          <View style={styles.reviewTotals}>
            <View>
              <Text style={styles.reviewTotalLabel}>Total por enviar</Text>
              <Text style={styles.reviewTotal}>{formatMoney(cartTotal)}</Text>
            </View>
            <Text style={styles.reviewHint}>Confirma antes de mandar a cocina.</Text>
          </View>

          <View style={styles.reviewActions}>
            <TouchableOpacity style={styles.reviewClearButton} onPress={requestClearCart} disabled={sending}>
              <Ionicons name="trash-outline" size={17} color="#B91C1C" />
              <Text style={styles.reviewClearText}>Vaciar</Text>
            </TouchableOpacity>
            <TouchableOpacity
              style={[styles.reviewSendButton, (sending || cartItems.length === 0) && styles.actionButtonDisabled]}
              onPress={sendOrder}
              disabled={sending || cartItems.length === 0}
              activeOpacity={0.88}
            >
              {sending ? (
                <ActivityIndicator color="#FFFFFF" />
              ) : (
                <>
                  <Ionicons name="send-outline" size={18} color="#FFFFFF" />
                  <Text style={styles.reviewSendText}>Enviar a cocina</Text>
                </>
              )}
            </TouchableOpacity>
          </View>
        </Pressable>
      </Pressable>
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

        {activeSplit ? (
          <TouchableOpacity style={styles.splitNotice} activeOpacity={0.88} onPress={() => setSplitVisible(true)}>
            <View style={styles.splitNoticeIcon}>
              <Ionicons name="git-branch-outline" size={18} color="#92400E" />
            </View>
            <View style={styles.splitNoticeCopy}>
              <Text style={styles.splitNoticeTitle}>Cuentas separadas activas</Text>
              <Text style={styles.splitNoticeText}>
                Cobra o cancela la division antes de agregar nuevos productos.
              </Text>
            </View>
            <Ionicons name="chevron-forward" size={19} color="#92400E" />
          </TouchableOpacity>
        ) : null}

        <View style={[styles.section, hasPendingCart && styles.pendingSection]}>
          <View style={styles.sectionHeader}>
            <View>
              <Text style={styles.sectionTitle}>Comanda pendiente</Text>
              <Text style={styles.sectionSubtitle}>{cartItems.length} productos sin enviar</Text>
            </View>
            {cartItems.length > 0 ? (
              <TouchableOpacity style={styles.clearButton} onPress={requestClearCart}>
                <Ionicons name="trash-outline" size={15} color="#B91C1C" />
                <Text style={styles.clearText}>Vaciar</Text>
              </TouchableOpacity>
            ) : null}
          </View>

          {cartItems.length > 0 ? (
            <>
              {cartItems.map(renderPendingItem)}
              <TouchableOpacity style={styles.sendButton} activeOpacity={0.88} onPress={requestSendOrder} disabled={sending}>
                {sending ? <ActivityIndicator color="#FFFFFF" /> : (
                  <>
                    <Ionicons name="list-outline" size={18} color="#FFFFFF" />
                    <Text style={styles.sendButtonText}>Revisar comanda - {formatMoney(cartTotal)}</Text>
                  </>
                )}
              </TouchableOpacity>
            </>
          ) : (
            <Text style={styles.emptyText}>
              {activeSplit
                ? 'La mesa tiene cuentas separadas activas. Termina esa division antes de agregar alimentos.'
                : 'Agrega alimentos desde el menu para preparar una comanda.'}
            </Text>
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
            <Text style={styles.emptyText}>Aún no hay productos enviados a cocina.</Text>
          ) : (
            sentGroups.map((group) => (
              <View key={group.key} style={styles.guestGroup}>
                <View style={styles.guestGroupHeader}>
                  <View style={styles.guestAvatar}>
                    <Text style={styles.guestAvatarText}>{group.name.trim().charAt(0).toUpperCase() || 'C'}</Text>
                  </View>
                  <View style={styles.guestCopy}>
                    <Text style={styles.guestName} numberOfLines={1}>{group.name}</Text>
                    <Text style={styles.guestMeta}>{group.items.length} productos pedidos</Text>
                  </View>
                  <Text style={styles.guestTotal}>{formatMoney(group.total)}</Text>
                </View>

                {group.items.map((item) => (
                  <View key={`${item.pedido_id}-${item.id}`} style={styles.sentItem}>
                    <Image source={item.imagen ? { uri: item.imagen } : PLACEHOLDER_FOOD} style={styles.sentImage} contentFit="cover" />
                    <View style={styles.lineCopy}>
                      <Text style={styles.lineName} numberOfLines={2}>{item.cantidad}x {item.nombre}</Text>
                      <Text style={styles.sentMeta} numberOfLines={1}>{item.pedido_folio ?? 'Comanda'} - {item.estado ?? 'pendiente'}</Text>
                      {getPersistedModifierLabels(item.modificadores).map((label, index) => (
                        <Text key={`${item.id}-modifier-${index}`} style={styles.extraText} numberOfLines={1}>{label}</Text>
                      ))}
                    </View>
                    <Text style={styles.linePrice}>{formatMoney(item.subtotal)}</Text>
                  </View>
                ))}
              </View>
            ))
          )}
        </View>
      </ScrollView>

      <View style={[styles.bottomActionBar, { paddingBottom: 10 + Math.max(insets.bottom, Platform.OS === 'android' ? 6 : 0) }]}>
        <TouchableOpacity
          style={[styles.bottomAction, styles.bottomPrimary, activeSplit && styles.actionButtonDisabled]}
          activeOpacity={0.88}
          onPress={() => void openMenu()}
          disabled={Boolean(activeSplit)}
        >
          <Ionicons name="add" size={20} color="#FFFFFF" />
          <Text style={styles.bottomPrimaryText}>Agregar</Text>
        </TouchableOpacity>
        <TouchableOpacity
          style={[styles.bottomAction, !hasPendingCart && styles.actionButtonDisabled]}
          activeOpacity={0.88}
          onPress={sendOrder}
          disabled={!hasPendingCart || sending}
        >
          {sending ? <ActivityIndicator color="#111827" /> : <Ionicons name="send-outline" size={18} color="#111827" />}
          <Text style={styles.bottomActionText}>Enviar</Text>
        </TouchableOpacity>
        <TouchableOpacity
          style={[styles.bottomAction, !canCloseAccount && styles.actionButtonDisabled]}
          activeOpacity={0.88}
          onPress={openCloseFlow}
          disabled={!canCloseAccount}
        >
          <Ionicons name="cash-outline" size={18} color="#111827" />
          <Text style={styles.bottomActionText}>Cobrar</Text>
        </TouchableOpacity>
      </View>

      <SplitAccountModal
        visible={splitVisible}
        tableId={tableId}
        restaurantId={restaurantId}
        tableLabel={tableLabel}
        items={sentItems}
        activeSplit={activeSplit}
        invoiceEnabled={invoiceEnabled}
        onClose={() => setSplitVisible(false)}
        onSplitChanged={() => { void accountQuery.refetch(); }}
        onPreviewTicket={openSplitTicketPreview}
      />

      <WaiterTicketPreviewModal
        visible={ticketVisible}
        status={ticketStatus}
        tableLabel={tableLabel}
        customerName={customerName}
        waiterName={waiterName}
        accountName={ticketAccountName}
        paymentMethod={ticketPaymentMethod}
        lines={ticketLines}
        tipAmount={ticketTipAmount}
        onClose={closeTicketPreview}
      />

      <Modal visible={menuVisible} animationType="slide" onRequestClose={() => setMenuVisible(false)}>
        <SafeAreaView style={styles.modalSafe} edges={['left', 'right']}>
          <View style={[styles.menuHeader, { paddingTop: Math.max(insets.top + 8, 14) }]}>
            <TouchableOpacity style={styles.iconButton} onPress={() => setMenuVisible(false)} activeOpacity={0.8}>
              <Ionicons name="close" size={22} color="#111827" />
            </TouchableOpacity>
            <View style={styles.headerCopy}>
              <Text style={styles.title}>Menú</Text>
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
              placeholder="Buscar platillo, categoría o descripción"
              placeholderTextColor="#94A3B8"
              style={styles.menuSearchInput}
              autoCapitalize="none"
              autoCorrect={false}
              returnKeyType="search"
            />
            {menuSearch ? (
              <TouchableOpacity
                accessibilityLabel="Limpiar búsqueda"
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
                          ? 'Intenta con otro nombre, ingrediente o categoría.'
                          : 'Esta categoría no tiene platillos disponibles.'}
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
                        <Text style={styles.productDescription} numberOfLines={2}>{item.descripcion ?? 'Sin descripción'}</Text>
                        <View style={styles.productMetaRow}>
                          {item.tiempo_preparacion_min ? (
                            <Text style={styles.productMeta}>{item.tiempo_preparacion_min} min</Text>
                          ) : null}
                          {item.categoria_nombre ? <Text style={styles.productMeta} numberOfLines={1}>{item.categoria_nombre}</Text> : null}
                        </View>
                      </View>
                      <View style={styles.priceRail}>
                        <Text style={styles.productPrice}>{formatMoney(item.precio)}</Text>
                        <TouchableOpacity
                          style={styles.quickAddButton}
                          activeOpacity={0.82}
                          disabled={!item.disponible || loadingProductId === item.id}
                          onPress={(event) => {
                            event.stopPropagation();
                            void openProduct(item, true);
                          }}
                          accessibilityLabel={`Agregar ${item.nombre}`}
                        >
                          {loadingProductId === item.id ? (
                            <ActivityIndicator size="small" color="#2563EB" />
                          ) : (
                            <Ionicons name="add" size={18} color={item.disponible ? '#FFFFFF' : '#94A3B8'} />
                          )}
                        </TouchableOpacity>
                      </View>
                    </TouchableOpacity>
                  )}
                />
              )}
            </View>
          </View>

          {cartItems.length > 0 ? (
            <TouchableOpacity
              style={[styles.menuCartBar, { bottom: 12 + Math.max(insets.bottom, Platform.OS === 'android' ? 8 : 0) }]}
              activeOpacity={0.9}
              onPress={requestSendOrder}
              accessibilityRole="button"
              accessibilityLabel="Revisar comanda pendiente"
            >
              <View>
                <Text style={styles.menuCartLabel}>{cartItems.length} productos pendientes</Text>
                <Text style={styles.menuCartTotal}>{formatMoney(cartTotal)}</Text>
              </View>
              <View style={styles.menuSendButton}>
                {sending ? <ActivityIndicator color="#FFFFFF" /> : <Text style={styles.menuSendText}>Revisar</Text>}
              </View>
            </TouchableOpacity>
          ) : null}

          {Boolean(selectedProduct) || productLoading ? (
            <Pressable
              style={styles.productOverlay}
              onPress={() => {
                Keyboard.dismiss();
                if (!productLoading) setSelectedProduct(null);
              }}
            >
              <KeyboardAvoidingView
                behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
                keyboardVerticalOffset={0}
                style={styles.productKeyboardAvoider}
              >
                <Pressable
                  style={[
                    styles.productSheet,
                    {
                      paddingBottom: keyboardVisible
                        ? 6
                        : 14 + Math.max(insets.bottom, Platform.OS === 'android' ? 8 : 0),
                    },
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

                      <ScrollView
                        style={styles.modifierScroll}
                        contentContainerStyle={styles.modifierContent}
                        showsVerticalScrollIndicator={false}
                        keyboardShouldPersistTaps="handled"
                        keyboardDismissMode={Platform.OS === 'ios' ? 'interactive' : 'on-drag'}
                      >
                        {(selectedProduct.modificadores ?? []).length === 0 ? (
                          <Text style={styles.noModsText}>Sin modificadores. Puedes agregar notas para cocina.</Text>
                        ) : null}
                        {(selectedProduct.modificadores ?? []).map((mod) => (
                          <View key={mod.id} style={styles.modifierBlock}>
                            <View style={styles.modifierHeader}>
                              <Text style={styles.modifierTitle}>{mod.nombre}</Text>
                              <Text style={styles.modifierHint}>
                                {mod.categoria === 'exclusion' ? 'Desmarca para omitir' : mod.tipo === 'radio' ? 'Elige una' : 'Opcional'}
                              </Text>
                            </View>
                            {mod.opciones.map((option) => {
                              const storedSelection = isOptionSelected(mod.id, option.id);
                              const included = option.tipo_modificador === 'exclusion';
                              const selected = included ? !storedSelection : storedSelection;
                              const canToggle = !included || option.puede_omitirse !== false;
                              const selectedOption = selectedMods
                                .find((group) => group.modificador_id === mod.id)
                                ?.opciones.find((item) => item.opcion_id === option.id);
                              const optionQuantity = Number(selectedOption?.cantidad ?? 1);
                              const maxQuantity = Math.max(1, Number(option.max_cantidad ?? 1));
                              return (
                                <TouchableOpacity
                                  key={option.id}
                                  style={[styles.optionRow, selected && styles.optionRowActive, !canToggle && styles.optionDisabled]}
                                  disabled={!canToggle}
                                  activeOpacity={0.85}
                                  onPress={() => toggleOption(mod, option)}
                                >
                                  <View style={[styles.checkbox, mod.tipo === 'radio' && styles.radioBox, selected && styles.checkboxActive]}>
                                    {selected ? <Ionicons name="checkmark" size={15} color="#FFFFFF" /> : null}
                                  </View>
                                  <Text style={styles.optionName} numberOfLines={2}>{option.nombre}</Text>
                                  {included ? (
                                    <Text style={[styles.includedBadge, !selected && styles.omittedBadge]}>
                                      {selected ? 'Incluido' : 'Omitir'}
                                    </Text>
                                  ) : null}
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
                          textAlignVertical="top"
                          returnKeyType="done"
                          blurOnSubmit
                          onSubmitEditing={Keyboard.dismiss}
                          maxLength={240}
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
              </KeyboardAvoidingView>
            </Pressable>
          ) : null}

          {pendingReviewVisible ? renderPendingReviewSheet(true) : null}
        </SafeAreaView>
      </Modal>

      <Modal
        visible={pendingReviewVisible && !menuVisible}
        transparent
        animationType="slide"
        onRequestClose={() => setPendingReviewVisible(false)}
      >
        {renderPendingReviewSheet(false)}
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
            <ScrollView contentContainerStyle={styles.closeModalContent} showsVerticalScrollIndicator={false}>
            <View style={styles.sheetHandle} />
            <Text style={styles.closeTitle}>Cerrar cuenta</Text>
            <Text style={styles.closeText}>Selecciona el método de pago para liberar {tableLabel}.</Text>
            <Text style={styles.closeTotal}>{formatMoney(closeGrandTotal)}</Text>
            <Text style={styles.closeBreakdown}>
              Subtotal {formatMoney(sentTotal)} · Propina {formatMoney(selectedTipAmount)}
            </Text>

            <TouchableOpacity style={styles.ticketPreviewButton} onPress={() => openAccountTicket('prebill')} disabled={closing}>
              <Ionicons name="receipt-outline" size={18} color="#2563EB" />
              <Text style={styles.ticketPreviewText}>Ver precuenta para imprimir</Text>
            </TouchableOpacity>

            <View style={styles.tipSection}>
              <Text style={styles.tipLabel}>Propina</Text>
              <View style={styles.tipOptions}>
                {([
                  ['none', 'Sin'],
                  ['10', '10%'],
                  ['15', '15%'],
                  ['20', '20%'],
                  ['custom', 'Otro'],
                ] as Array<[TipMode, string]>).map(([value, label]) => {
                  const selected = tipMode === value;
                  return (
                    <TouchableOpacity
                      key={value}
                      style={[styles.tipChip, selected && styles.tipChipActive]}
                      onPress={() => setTipMode(value)}
                      activeOpacity={0.85}
                    >
                      <Text style={[styles.tipChipText, selected && styles.tipChipTextActive]}>{label}</Text>
                    </TouchableOpacity>
                  );
                })}
              </View>
              {tipMode === 'custom' ? (
                <TextInput
                  value={customTip}
                  onChangeText={setCustomTip}
                  keyboardType="decimal-pad"
                  placeholder="$0.00"
                  placeholderTextColor="#94A3B8"
                  style={styles.tipInput}
                />
              ) : null}
            </View>

            <InvoiceRequestForm
              enabled={invoiceEnabled}
              required={invoiceRequired}
              data={invoiceFiscalData}
              saveToProfile={invoiceSaveToProfile}
              disabled={closing}
              onRequiredChange={setInvoiceRequired}
              onDataChange={setInvoiceFiscalData}
              onSaveToProfileChange={setInvoiceSaveToProfile}
            />

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
            </ScrollView>
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
    paddingHorizontal: 18,
    paddingVertical: 12,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    backgroundColor: '#F4F6F8',
  },
  menuHeader: {
    paddingHorizontal: 16,
    paddingVertical: 12,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    backgroundColor: '#FFFFFF',
    borderBottomWidth: 1,
    borderBottomColor: '#E5E7EB',
  },
  menuSearchWrap: {
    minHeight: 62,
    marginHorizontal: 12,
    marginVertical: 12,
    borderRadius: 18,
    borderWidth: 1,
    borderColor: '#E2E8F0',
    backgroundColor: '#FFFFFF',
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 15,
    gap: 12,
  },
  menuSearchInput: {
    flex: 1,
    minHeight: 60,
    color: '#111827',
    fontSize: 14,
    fontWeight: '800',
  },
  menuSearchClear: {
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: '#F1F5F9',
    alignItems: 'center',
    justifyContent: 'center',
  },
  iconButton: {
    width: 52,
    height: 52,
    borderRadius: 18,
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
    fontSize: 27,
    fontWeight: '900',
    color: '#111827',
  },
  subtitle: {
    marginTop: 2,
    fontSize: 15,
    color: '#64748B',
    fontWeight: '800',
  },
  content: {
    paddingHorizontal: 16,
    paddingBottom: 142,
    gap: 16,
  },
  accountPanel: {
    borderRadius: 22,
    backgroundColor: '#111827',
    padding: 18,
  },
  accountPanelTop: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 10,
  },
  accountBadge: {
    minHeight: 40,
    borderRadius: 999,
    backgroundColor: 'rgba(255,255,255,0.12)',
    paddingHorizontal: 13,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  accountBadgeText: {
    color: '#FFFFFF',
    fontSize: 14,
    fontWeight: '900',
  },
  accountMetaText: {
    color: '#CBD5E1',
    fontSize: 14,
    fontWeight: '800',
  },
  totalText: {
    marginTop: 12,
    fontSize: 46,
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
    minHeight: 72,
    borderRadius: 16,
    backgroundColor: 'rgba(255,255,255,0.10)',
    paddingHorizontal: 10,
    justifyContent: 'center',
  },
  statValue: {
    color: '#FFFFFF',
    fontWeight: '900',
    fontSize: 20,
  },
  statLabel: {
    marginTop: 3,
    color: '#CBD5E1',
    fontSize: 13,
    fontWeight: '800',
  },
  accountPeople: {
    marginTop: 12,
    gap: 4,
  },
  personLine: {
    color: '#E5E7EB',
    fontSize: 15,
    fontWeight: '800',
  },
  splitNotice: {
    borderRadius: 18,
    borderWidth: 1,
    borderColor: '#FDE68A',
    backgroundColor: '#FFFBEB',
    padding: 16,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  splitNoticeIcon: {
    width: 48,
    height: 48,
    borderRadius: 15,
    backgroundColor: '#FEF3C7',
    alignItems: 'center',
    justifyContent: 'center',
  },
  splitNoticeCopy: {
    flex: 1,
    minWidth: 0,
  },
  splitNoticeTitle: {
    fontSize: 17,
    fontWeight: '900',
    color: '#78350F',
  },
  splitNoticeText: {
    marginTop: 2,
    fontSize: 14,
    fontWeight: '800',
    color: '#92400E',
    lineHeight: 17,
  },
  actionGrid: {
    flexDirection: 'row',
    gap: 10,
  },
  primaryActionButton: {
    flex: 1.2,
    minHeight: 66,
    borderRadius: 18,
    backgroundColor: '#2563EB',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
  },
  primaryActionText: {
    fontSize: 17,
    fontWeight: '900',
    color: '#FFFFFF',
  },
  secondaryActionButton: {
    flex: 1,
    minHeight: 66,
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
    fontSize: 17,
    fontWeight: '900',
    color: '#111827',
  },
  actionButtonDisabled: {
    opacity: 0.45,
  },
  section: {
    borderRadius: 20,
    backgroundColor: '#FFFFFF',
    padding: 16,
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
    fontSize: 20,
    fontWeight: '900',
    color: '#111827',
  },
  sectionSubtitle: {
    marginTop: 2,
    fontSize: 14,
    color: '#64748B',
    fontWeight: '800',
  },
  clearButton: {
    minHeight: 44,
    borderRadius: 14,
    paddingHorizontal: 13,
    backgroundColor: '#FEF2F2',
    flexDirection: 'row',
    alignItems: 'center',
    gap: 5,
  },
  clearText: {
    color: '#B91C1C',
    fontWeight: '900',
    fontSize: 14,
  },
  pendingItem: {
    paddingVertical: 16,
    borderTopWidth: 1,
    borderTopColor: '#F1F5F9',
    flexDirection: 'row',
    gap: 10,
  },
  itemQtyBadge: {
    width: 42,
    height: 42,
    borderRadius: 14,
    backgroundColor: '#EFF6FF',
    alignItems: 'center',
    justifyContent: 'center',
  },
  itemQtyText: {
    fontSize: 16,
    fontWeight: '900',
    color: '#2563EB',
  },
  sentItem: {
    paddingVertical: 16,
    borderTopWidth: 1,
    borderTopColor: '#F1F5F9',
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  guestGroup: {
    marginTop: 10,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: '#E2E8F0',
    backgroundColor: '#F8FAFC',
    overflow: 'hidden',
  },
  guestGroupHeader: {
    minHeight: 72,
    paddingHorizontal: 14,
    paddingVertical: 12,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    backgroundColor: '#EEF2FF',
  },
  guestAvatar: {
    width: 44,
    height: 44,
    borderRadius: 14,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#111827',
  },
  guestAvatarText: {
    color: '#FFFFFF',
    fontWeight: '900',
    fontSize: 16,
  },
  guestCopy: {
    flex: 1,
    minWidth: 0,
  },
  guestName: {
    color: '#111827',
    fontSize: 17,
    fontWeight: '900',
  },
  guestMeta: {
    marginTop: 2,
    color: '#64748B',
    fontSize: 13,
    fontWeight: '800',
  },
  guestTotal: {
    color: '#111827',
    fontSize: 17,
    fontWeight: '900',
  },
  sentImage: {
    width: 58,
    height: 58,
    borderRadius: 16,
    backgroundColor: '#F1F5F9',
  },
  lineCopy: {
    flex: 1,
    minWidth: 0,
  },
  lineName: {
    fontSize: 17,
    fontWeight: '900',
    color: '#111827',
  },
  extraText: {
    marginTop: 3,
    fontSize: 14,
    color: '#64748B',
    fontWeight: '700',
  },
  noteText: {
    marginTop: 4,
    fontSize: 14,
    color: '#92400E',
    fontWeight: '800',
  },
  sentMeta: {
    marginTop: 4,
    fontSize: 14,
    color: '#64748B',
    fontWeight: '700',
  },
  lineActions: {
    alignItems: 'flex-end',
    gap: 8,
  },
  linePrice: {
    fontSize: 17,
    fontWeight: '900',
    color: '#111827',
  },
  qtyRow: {
    flexDirection: 'row',
    gap: 6,
  },
  qtyButton: {
    width: 40,
    height: 40,
    borderRadius: 14,
    backgroundColor: '#F1F5F9',
    alignItems: 'center',
    justifyContent: 'center',
  },
  sendButton: {
    marginTop: 14,
    minHeight: 64,
    borderRadius: 18,
    backgroundColor: '#111827',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
  },
  sendButtonText: {
    color: '#FFFFFF',
    fontWeight: '900',
    fontSize: 17,
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
    width: 124,
    backgroundColor: '#FFFFFF',
    borderRightWidth: 1,
    borderRightColor: '#E5E7EB',
  },
  categoryList: {
    padding: 10,
    gap: 10,
    paddingBottom: 116,
  },
  categoryTab: {
    minHeight: 104,
    borderRadius: 18,
    padding: 10,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 6,
  },
  categoryTabActive: {
    backgroundColor: '#111827',
  },
  categoryIconWrap: {
    width: 42,
    height: 42,
    borderRadius: 14,
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
    fontSize: 13,
    lineHeight: 16,
  },
  categoryTextActive: {
    color: '#FFFFFF',
  },
  categoryCount: {
    color: '#94A3B8',
    fontSize: 13,
    fontWeight: '900',
  },
  categoryCountActive: {
    color: '#CBD5E1',
  },
  productPane: {
    flex: 1,
  },
  productList: {
    padding: 12,
    gap: 12,
    paddingBottom: 24,
  },
  productListWithCart: {
    paddingBottom: 126,
  },
  productRow: {
    minHeight: 140,
    borderRadius: 20,
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
    width: 82,
    height: 98,
    borderRadius: 16,
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
    fontSize: 17,
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
    fontSize: 14,
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
    maxWidth: 110,
    borderRadius: 999,
    paddingHorizontal: 9,
    paddingVertical: 5,
    backgroundColor: '#F1F5F9',
    color: '#475569',
    fontSize: 12,
    fontWeight: '900',
  },
  priceRail: {
    alignSelf: 'stretch',
    alignItems: 'flex-end',
    justifyContent: 'space-between',
    gap: 8,
  },
  productPrice: {
    fontSize: 17,
    fontWeight: '900',
    color: '#111827',
  },
  quickAddButton: {
    width: 48,
    height: 48,
    borderRadius: 16,
    backgroundColor: '#2563EB',
    alignItems: 'center',
    justifyContent: 'center',
  },
  bottomActionBar: {
    position: 'absolute',
    left: 0,
    right: 0,
    bottom: 0,
    paddingTop: 12,
    paddingHorizontal: 14,
    borderTopWidth: 1,
    borderTopColor: '#E5E7EB',
    backgroundColor: 'rgba(255,255,255,0.98)',
    flexDirection: 'row',
    gap: 8,
  },
  bottomAction: {
    flex: 1,
    minHeight: 64,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: '#D1D5DB',
    backgroundColor: '#FFFFFF',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 6,
  },
  bottomPrimary: {
    flex: 1.15,
    borderColor: '#111827',
    backgroundColor: '#111827',
  },
  bottomActionText: {
    color: '#111827',
    fontSize: 15,
    fontWeight: '900',
  },
  bottomPrimaryText: {
    color: '#FFFFFF',
    fontSize: 15,
    fontWeight: '900',
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
    left: 136,
    right: 12,
    bottom: 12,
    minHeight: 84,
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
    fontSize: 14,
    fontWeight: '800',
  },
  menuCartTotal: {
    marginTop: 2,
    color: '#FFFFFF',
    fontSize: 24,
    fontWeight: '900',
  },
  menuSendButton: {
    minWidth: 112,
    minHeight: 54,
    borderRadius: 16,
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
  productKeyboardAvoider: {
    flex: 1,
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
  optionDisabled: { opacity: 0.62 },
  includedBadge: {
    fontSize: 10,
    fontWeight: '900',
    color: '#2F6B4F',
    backgroundColor: '#E9F6EF',
    paddingHorizontal: 7,
    paddingVertical: 4,
    borderRadius: 999,
  },
  omittedBadge: {
    color: '#9A5B27',
    backgroundColor: '#FFF1E4',
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
  reviewOverlay: {
    flex: 1,
    backgroundColor: 'rgba(15, 23, 42, 0.52)',
    justifyContent: 'flex-end',
  },
  reviewInlineOverlay: {
    ...StyleSheet.absoluteFillObject,
  },
  reviewSheet: {
    maxHeight: '86%',
    borderTopLeftRadius: 26,
    borderTopRightRadius: 26,
    backgroundColor: '#FFFFFF',
    paddingHorizontal: 16,
    paddingTop: 10,
  },
  reviewHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  reviewIcon: {
    width: 42,
    height: 42,
    borderRadius: 14,
    backgroundColor: '#111827',
    alignItems: 'center',
    justifyContent: 'center',
  },
  reviewHeaderCopy: {
    flex: 1,
    minWidth: 0,
  },
  reviewTitle: {
    color: '#111827',
    fontSize: 20,
    fontWeight: '900',
  },
  reviewSubtitle: {
    marginTop: 2,
    color: '#64748B',
    fontSize: 12,
    fontWeight: '800',
  },
  reviewList: {
    marginTop: 14,
  },
  reviewListContent: {
    gap: 10,
    paddingBottom: 8,
  },
  reviewItem: {
    minHeight: 94,
    borderRadius: 18,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    backgroundColor: '#F8FAFC',
    padding: 12,
    flexDirection: 'row',
    gap: 12,
  },
  reviewImage: {
    width: 54,
    height: 62,
    borderRadius: 14,
    backgroundColor: '#E2E8F0',
  },
  reviewCopy: {
    flex: 1,
    minWidth: 0,
  },
  reviewTitleRow: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 10,
  },
  reviewName: {
    flex: 1,
    color: '#111827',
    fontSize: 17,
    fontWeight: '900',
  },
  reviewPrice: {
    color: '#111827',
    fontSize: 13,
    fontWeight: '900',
  },
  reviewMeta: {
    marginTop: 4,
    color: '#64748B',
    fontSize: 12,
    fontWeight: '800',
  },
  reviewNoteRow: {
    marginTop: 7,
    borderRadius: 12,
    backgroundColor: '#FFFBEB',
    paddingHorizontal: 9,
    paddingVertical: 7,
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 7,
  },
  reviewNote: {
    flex: 1,
    color: '#92400E',
    fontSize: 12,
    fontWeight: '800',
    lineHeight: 19,
  },
  reviewQtyActions: {
    justifyContent: 'space-between',
    alignItems: 'center',
    gap: 8,
  },
  reviewTotals: {
    marginTop: 8,
    borderRadius: 18,
    backgroundColor: '#F1F5F9',
    padding: 12,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 12,
  },
  reviewTotalLabel: {
    color: '#64748B',
    fontSize: 11,
    fontWeight: '900',
    textTransform: 'uppercase',
  },
  reviewTotal: {
    marginTop: 2,
    color: '#111827',
    fontSize: 26,
    fontWeight: '900',
  },
  reviewHint: {
    flex: 1,
    textAlign: 'right',
    color: '#475569',
    fontSize: 12,
    fontWeight: '800',
    lineHeight: 20,
  },
  reviewActions: {
    marginTop: 12,
    flexDirection: 'row',
    gap: 10,
  },
  reviewClearButton: {
    minHeight: 52,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: '#FECACA',
    backgroundColor: '#FEF2F2',
    paddingHorizontal: 16,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 7,
  },
  reviewClearText: {
    color: '#B91C1C',
    fontWeight: '900',
  },
  reviewSendButton: {
    flex: 1,
    minHeight: 52,
    borderRadius: 16,
    backgroundColor: '#111827',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
  },
  reviewSendText: {
    color: '#FFFFFF',
    fontSize: 15,
    fontWeight: '900',
  },
  closeOverlay: {
    flex: 1,
    backgroundColor: 'rgba(15, 23, 42, 0.50)',
    justifyContent: 'flex-end',
  },
  closeModal: {
    maxHeight: '92%',
    borderTopLeftRadius: 26,
    borderTopRightRadius: 26,
    backgroundColor: '#FFFFFF',
    paddingHorizontal: 18,
    paddingTop: 10,
    paddingBottom: 22,
  },
  closeModalContent: {
    paddingBottom: 4,
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
    marginBottom: 4,
    fontSize: 34,
    fontWeight: '900',
    color: '#111827',
  },
  closeBreakdown: {
    marginBottom: 12,
    color: '#64748B',
    fontSize: 13,
    fontWeight: '800',
  },
  ticketPreviewButton: {
    minHeight: 50,
    marginBottom: 12,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: '#BFDBFE',
    backgroundColor: '#EFF6FF',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
  },
  ticketPreviewText: {
    color: '#1D4ED8',
    fontSize: 14,
    fontWeight: '900',
  },
  tipSection: {
    marginBottom: 12,
  },
  tipLabel: {
    marginBottom: 8,
    color: '#334155',
    fontSize: 13,
    fontWeight: '900',
  },
  tipOptions: {
    flexDirection: 'row',
    gap: 8,
  },
  tipChip: {
    flex: 1,
    minHeight: 38,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: '#E2E8F0',
    backgroundColor: '#FFFFFF',
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 6,
  },
  tipChipActive: {
    borderColor: '#BFDBFE',
    backgroundColor: '#EFF6FF',
  },
  tipChipText: {
    color: '#475569',
    fontSize: 12,
    fontWeight: '900',
  },
  tipChipTextActive: {
    color: '#1D4ED8',
  },
  tipInput: {
    minHeight: 44,
    marginTop: 8,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: '#CBD5E1',
    paddingHorizontal: 12,
    color: '#111827',
    fontSize: 16,
    fontWeight: '900',
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
