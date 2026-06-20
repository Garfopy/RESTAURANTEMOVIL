import React, { useEffect, useMemo, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Modal,
  ScrollView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
  type View as ViewType,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import * as Haptics from 'expo-haptics';
import { Gesture, GestureDetector } from 'react-native-gesture-handler';
import Animated, {
  runOnJS,
  useAnimatedStyle,
  useSharedValue,
  withSpring,
} from 'react-native-reanimated';
import { getApiError } from '../../services/api';
import {
  cancelWaiterSplit,
  createWaiterSplit,
  payWaiterSplitAccount,
  type WaiterAccountItem,
  type WaiterPaymentMethod,
  type WaiterSplit,
} from '../../services/waiter.service';

type DraftAccount = { key: string; name: string };
type Unit = { key: string; itemId: number; name: string; price: number; image?: string | null };
type Zone = { key: string; x: number; y: number; width: number; height: number };

type Props = {
  visible: boolean;
  tableId: number;
  restaurantId: number;
  tableLabel: string;
  items: WaiterAccountItem[];
  activeSplit?: WaiterSplit | null;
  onClose: () => void;
  onSplitChanged: (split: WaiterSplit | null) => void;
  onFullyPaid: () => void;
};

const PAYMENT_METHODS: Array<[WaiterPaymentMethod, string, keyof typeof Ionicons.glyphMap]> = [
  ['efectivo', 'Efectivo', 'cash-outline'],
  ['tarjeta', 'Tarjeta', 'card-outline'],
  ['transferencia', 'Transferencia', 'swap-horizontal-outline'],
];
const PLACEHOLDER_FOOD = require('../../assets/placeholder-food.jpg');

function money(value: number): string {
  return `$${value.toFixed(2)}`;
}

function DraggableUnit({
  unit,
  selected,
  disabled,
  onPress,
  onDragStart,
  onDragMove,
  onDrop,
  onDragFinish,
}: {
  unit: Unit;
  selected: boolean;
  disabled: boolean;
  onPress: () => void;
  onDragStart: () => void;
  onDragMove: (x: number, y: number) => void;
  onDrop: (x: number, y: number) => void;
  onDragFinish: () => void;
}) {
  const x = useSharedValue(0);
  const y = useSharedValue(0);
  const scale = useSharedValue(1);
  const gesture = useMemo(
    () => Gesture.Pan()
      .enabled(!disabled)
      .activateAfterLongPress(140)
      .onBegin(() => {
        scale.value = withSpring(1.05);
        runOnJS(onDragStart)();
      })
      .onUpdate((event) => {
        x.value = event.translationX;
        y.value = event.translationY;
        runOnJS(onDragMove)(event.absoluteX, event.absoluteY);
      })
      .onEnd((event) => {
        runOnJS(onDrop)(event.absoluteX, event.absoluteY);
        x.value = withSpring(0, { damping: 18, stiffness: 220 });
        y.value = withSpring(0, { damping: 18, stiffness: 220 });
        scale.value = withSpring(1);
      })
      .onFinalize(() => {
        x.value = withSpring(0);
        y.value = withSpring(0);
        scale.value = withSpring(1);
        runOnJS(onDragFinish)();
      }),
    [disabled, onDragFinish, onDragMove, onDragStart, onDrop, scale, x, y]
  );
  const animatedStyle = useAnimatedStyle(() => ({
    transform: [{ translateX: x.value }, { translateY: y.value }, { scale: scale.value }],
    zIndex: scale.value > 1 ? 100 : 1,
    elevation: scale.value > 1 ? 12 : 0,
  }));

  return (
    <GestureDetector gesture={gesture}>
      <Animated.View style={[styles.unitCard, selected && styles.unitCardSelected, animatedStyle]}>
        <TouchableOpacity
          style={styles.unitPressable}
          onPress={onPress}
          disabled={disabled}
          accessibilityRole="button"
          accessibilityLabel={`${unit.name}, ${money(unit.price)}. Manten presionado para arrastrar.`}
        >
          <Ionicons name="reorder-three" size={21} color="#64748B" />
          <Image
            source={unit.image ? { uri: unit.image } : PLACEHOLDER_FOOD}
            style={styles.unitImage}
            contentFit="cover"
            transition={120}
          />
          <View style={styles.unitCopy}>
            <Text style={styles.unitName} numberOfLines={1}>{unit.name}</Text>
            <Text style={styles.unitPrice}>{money(unit.price)}</Text>
          </View>
          {selected ? <Ionicons name="checkmark-circle" size={20} color="#2563EB" /> : null}
        </TouchableOpacity>
      </Animated.View>
    </GestureDetector>
  );
}

export function SplitAccountModal({
  visible,
  tableId,
  restaurantId,
  tableLabel,
  items,
  activeSplit,
  onClose,
  onSplitChanged,
  onFullyPaid,
}: Props) {
  const [accounts, setAccounts] = useState<DraftAccount[]>([]);
  const [allocation, setAllocation] = useState<Record<string, string | null>>({});
  const [selectedUnit, setSelectedUnit] = useState<string | null>(null);
  const [hoveredZone, setHoveredZone] = useState<string | null>(null);
  const [dragging, setDragging] = useState(false);
  const [saving, setSaving] = useState(false);
  const [split, setSplit] = useState<WaiterSplit | null>(activeSplit ?? null);
  const [paymentMethod, setPaymentMethod] = useState<WaiterPaymentMethod>('efectivo');
  const zoneRefs = useRef<Record<string, ViewType | null>>({});
  const zones = useRef<Zone[]>([]);
  const nextAccountNumber = useRef(3);

  const units = useMemo<Unit[]>(() => items.flatMap((item) =>
    Array.from({ length: item.cantidad }, (_, index) => ({
      key: `${item.id}:${index + 1}`,
      itemId: item.id,
      name: item.nombre,
      price: Number(item.precio_unit),
      image: item.imagen,
    }))
  ), [items]);

  useEffect(() => {
    setSplit(activeSplit ?? null);
  }, [activeSplit]);

  useEffect(() => {
    if (!visible || activeSplit) return;
    setAccounts([
      { key: 'account-1', name: 'Cuenta 1' },
      { key: 'account-2', name: 'Cuenta 2' },
    ]);
    nextAccountNumber.current = 3;
    setAllocation(Object.fromEntries(units.map((unit) => [unit.key, null])));
    setSelectedUnit(null);
    setHoveredZone(null);
  }, [activeSplit, units, visible]);

  const sourceItemsById = useMemo(() => Object.fromEntries(items.map((item) => [item.id, item])), [items]);
  const unassigned = units.filter((unit) => !allocation[unit.key]);
  const accountUnits = (key: string) => units.filter((unit) => allocation[unit.key] === key);
  const accountTotal = (key: string) => accountUnits(key).reduce((sum, unit) => sum + unit.price, 0);

  function measureZones() {
    const keys = ['unassigned', ...accounts.map((account) => account.key)];
    const measured: Zone[] = [];
    keys.forEach((key) => {
      zoneRefs.current[key]?.measureInWindow((x, y, width, height) => {
        measured.push({ key, x, y, width, height });
        zones.current = measured;
      });
    });
  }

  function zoneAt(x: number, y: number): string | null {
    return zones.current.find((zone) =>
      x >= zone.x && x <= zone.x + zone.width && y >= zone.y && y <= zone.y + zone.height
    )?.key ?? null;
  }

  function handleDragStart() {
    setDragging(true);
    measureZones();
    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
  }

  function handleDragMove(x: number, y: number) {
    const next = zoneAt(x, y);
    setHoveredZone((current) => current === next ? current : next);
  }

  function moveUnit(unitKey: string, zoneKey: string | null) {
    const destination = zoneKey === 'unassigned' ? null : zoneKey;
    setAllocation((current) => ({ ...current, [unitKey]: destination }));
    setSelectedUnit(null);
    void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
  }

  function handleDrop(unitKey: string, x: number, y: number) {
    const destination = zoneAt(x, y);
    setDragging(false);
    setHoveredZone(null);
    if (destination) moveUnit(unitKey, destination);
  }

  function handleDragFinish() {
    setDragging(false);
    setHoveredZone(null);
  }

  function handleZonePress(zoneKey: string) {
    if (selectedUnit) moveUnit(selectedUnit, zoneKey);
  }

  function addAccount() {
    const number = nextAccountNumber.current++;
    setAccounts((current) => [...current, { key: `account-${number}`, name: `Cuenta ${number}` }]);
  }

  function removeAccount(key: string) {
    if (accounts.length <= 2 || accountUnits(key).length > 0) return;
    setAccounts((current) => current.filter((account) => account.key !== key));
  }

  async function createSplit() {
    if (unassigned.length > 0) {
      Alert.alert('Faltan productos', 'Asigna todos los productos antes de continuar.');
      return;
    }
    if (accounts.some((account) => accountUnits(account.key).length === 0)) {
      Alert.alert('Cuenta vacia', 'Cada cuenta debe tener al menos un producto.');
      return;
    }

    try {
      setSaving(true);
      const created = await createWaiterSplit({
        tableId,
        restaurantId,
        accounts: accounts.map((account) => {
          const grouped = new Map<number, number>();
          accountUnits(account.key).forEach((unit) => grouped.set(unit.itemId, (grouped.get(unit.itemId) ?? 0) + 1));
          return {
            name: account.name,
            items: Array.from(grouped, ([pedido_item_id, cantidad]) => ({ pedido_item_id, cantidad })),
          };
        }),
      });
      setSplit(created);
      onSplitChanged(created);
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
    } catch (error) {
      Alert.alert('No se pudo guardar el reparto', getApiError(error));
    } finally {
      setSaving(false);
    }
  }

  async function payCurrentAccount() {
    const current = split?.accounts.find((account) => account.estado === 'pendiente');
    if (!split || !current) return;
    try {
      setSaving(true);
      const result = await payWaiterSplitAccount({
        tableId,
        restaurantId,
        splitId: split.id,
        accountId: current.id,
        metodoPago: paymentMethod,
      });
      setSplit(result.split);
      onSplitChanged(result.split);
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
      if (result.closed) onFullyPaid();
    } catch (error) {
      Alert.alert('No se pudo registrar el pago', getApiError(error));
    } finally {
      setSaving(false);
    }
  }

  function requestCancelSplit() {
    if (!split || split.paid_count > 0) return;
    Alert.alert('Cancelar division', 'Los productos volveran a una sola cuenta.', [
      { text: 'Conservar', style: 'cancel' },
      {
        text: 'Cancelar division',
        style: 'destructive',
        onPress: async () => {
          try {
            setSaving(true);
            await cancelWaiterSplit({ tableId, restaurantId, splitId: split.id });
            setSplit(null);
            onSplitChanged(null);
            onClose();
          } catch (error) {
            Alert.alert('No se pudo cancelar', getApiError(error));
          } finally {
            setSaving(false);
          }
        },
      },
    ]);
  }

  const currentPayment = split?.accounts.find((account) => account.estado === 'pendiente') ?? null;

  return (
    <Modal visible={visible} animationType="slide" onRequestClose={onClose}>
      <SafeAreaView style={styles.safe}>
        <View style={styles.header}>
          <TouchableOpacity style={styles.iconButton} onPress={onClose} disabled={saving}>
            <Ionicons name="close" size={23} color="#111827" />
          </TouchableOpacity>
          <View style={styles.headerCopy}>
            <Text style={styles.title}>{split ? 'Cobrar cuentas' : 'Separar cuenta'}</Text>
            <Text style={styles.subtitle}>{tableLabel}</Text>
          </View>
          <View style={styles.countBadge}>
            <Text style={styles.countBadgeText}>{split ? `${split.paid_count}/${split.accounts_count}` : accounts.length}</Text>
          </View>
        </View>

        {split ? (
          <ScrollView contentContainerStyle={styles.paymentContent} showsVerticalScrollIndicator={false}>
            <View style={styles.progressCard}>
              <Text style={styles.progressEyebrow}>PROGRESO DE COBRO</Text>
              <Text style={styles.progressTitle}>{split.paid_count} de {split.accounts_count} pagadas</Text>
              <View style={styles.progressTrack}>
                <View style={[styles.progressFill, { width: `${(split.paid_count / split.accounts_count) * 100}%` }]} />
              </View>
            </View>

            {split.accounts.map((account) => {
              const paid = account.estado === 'pagada';
              const active = currentPayment?.id === account.id;
              return (
                <View key={account.id} style={[styles.paymentAccount, active && styles.paymentAccountActive]}>
                  <View style={[styles.accountNumber, paid && styles.accountNumberPaid]}>
                    <Ionicons name={paid ? 'checkmark' : 'receipt-outline'} size={18} color={paid ? '#FFFFFF' : '#2563EB'} />
                  </View>
                  <View style={styles.paymentAccountCopy}>
                    <Text style={styles.paymentAccountName}>{account.nombre}</Text>
                    <Text style={styles.paymentAccountMeta}>{paid ? `Pagada con ${account.metodo_pago}` : `${account.items.reduce((sum, item) => sum + item.cantidad, 0)} productos`}</Text>
                  </View>
                  <Text style={styles.paymentAccountTotal}>{money(account.total)}</Text>
                </View>
              );
            })}

            {currentPayment ? (
              <View style={styles.checkoutCard}>
                <Text style={styles.checkoutLabel}>Cobrar ahora</Text>
                <View style={styles.checkoutHeading}>
                  <Text style={styles.checkoutName}>{currentPayment.nombre}</Text>
                  <Text style={styles.checkoutTotal}>{money(currentPayment.total)}</Text>
                </View>
                <View style={styles.checkoutItems}>
                  {currentPayment.items.map((item) => (
                    <View key={item.pedido_item_id} style={styles.checkoutItemRow}>
                      <Text style={styles.checkoutItemName} numberOfLines={1}>
                        {item.cantidad}x {sourceItemsById[item.pedido_item_id]?.nombre ?? 'Producto'}
                      </Text>
                      <Text style={styles.checkoutItemPrice}>{money(item.subtotal)}</Text>
                    </View>
                  ))}
                </View>
                {PAYMENT_METHODS.map(([value, label, icon]) => {
                  const selected = paymentMethod === value;
                  return (
                    <TouchableOpacity
                      key={value}
                      style={[styles.paymentOption, selected && styles.paymentOptionActive]}
                      onPress={() => setPaymentMethod(value)}
                      disabled={saving}
                    >
                      <Ionicons name={icon} size={21} color={selected ? '#2563EB' : '#64748B'} />
                      <Text style={styles.paymentOptionText}>{label}</Text>
                      <Ionicons name={selected ? 'checkmark-circle' : 'ellipse-outline'} size={22} color={selected ? '#2563EB' : '#CBD5E1'} />
                    </TouchableOpacity>
                  );
                })}
                <TouchableOpacity style={styles.payButton} onPress={payCurrentAccount} disabled={saving}>
                  {saving ? <ActivityIndicator color="#FFFFFF" /> : <Text style={styles.payButtonText}>Confirmar pago de {money(currentPayment.total)}</Text>}
                </TouchableOpacity>
              </View>
            ) : null}

            {split.paid_count === 0 ? (
              <TouchableOpacity style={styles.cancelSplitButton} onPress={requestCancelSplit} disabled={saving}>
                <Text style={styles.cancelSplitText}>Cancelar division</Text>
              </TouchableOpacity>
            ) : (
              <Text style={styles.lockedText}>El reparto queda bloqueado despues del primer pago.</Text>
            )}
          </ScrollView>
        ) : (
          <>
            <View style={styles.tipBar}>
              <Ionicons name="hand-left-outline" size={18} color="#1D4ED8" />
              <Text style={styles.tipText}>Manten presionado y arrastra una unidad, o tocala y elige su cuenta.</Text>
            </View>
            <ScrollView
              contentContainerStyle={styles.splitContent}
              scrollEnabled={!dragging}
              onScrollEndDrag={measureZones}
              onMomentumScrollEnd={measureZones}
            >
              <TouchableOpacity activeOpacity={1} onPress={() => handleZonePress('unassigned')}>
                <View
                  ref={(ref) => { zoneRefs.current.unassigned = ref; }}
                  onLayout={measureZones}
                  style={[styles.zone, styles.unassignedZone, hoveredZone === 'unassigned' && styles.zoneHovered]}
                >
                  <View style={styles.zoneHeader}>
                    <View>
                      <Text style={styles.zoneTitle}>Sin asignar</Text>
                      <Text style={styles.zoneMeta}>{unassigned.length} unidades</Text>
                    </View>
                    <Text style={styles.zoneTotal}>{money(unassigned.reduce((sum, unit) => sum + unit.price, 0))}</Text>
                  </View>
                  {unassigned.length ? unassigned.map((unit) => (
                    <DraggableUnit
                      key={unit.key}
                      unit={unit}
                      selected={selectedUnit === unit.key}
                      disabled={saving}
                      onPress={() => setSelectedUnit((current) => current === unit.key ? null : unit.key)}
                      onDragStart={handleDragStart}
                      onDragMove={handleDragMove}
                      onDrop={(x, y) => handleDrop(unit.key, x, y)}
                      onDragFinish={handleDragFinish}
                    />
                  )) : <Text style={styles.zoneEmpty}>Todos los productos estan asignados.</Text>}
                </View>
              </TouchableOpacity>

              {accounts.map((account) => {
                const assigned = accountUnits(account.key);
                return (
                  <TouchableOpacity key={account.key} activeOpacity={1} onPress={() => handleZonePress(account.key)}>
                    <View
                      ref={(ref) => { zoneRefs.current[account.key] = ref; }}
                      onLayout={measureZones}
                      style={[styles.zone, hoveredZone === account.key && styles.zoneHovered]}
                    >
                      <View style={styles.zoneHeader}>
                        <View style={styles.accountTitleRow}>
                          <View style={styles.accountNumber}><Text style={styles.accountNumberText}>{account.name.replace('Cuenta ', '')}</Text></View>
                          <View>
                            <Text style={styles.zoneTitle}>{account.name}</Text>
                            <Text style={styles.zoneMeta}>{assigned.length} unidades</Text>
                          </View>
                        </View>
                        <View style={styles.accountRight}>
                          <Text style={styles.zoneTotal}>{money(accountTotal(account.key))}</Text>
                          {accounts.length > 2 && assigned.length === 0 ? (
                            <TouchableOpacity onPress={() => removeAccount(account.key)} accessibilityLabel={`Eliminar ${account.name}`}>
                              <Ionicons name="trash-outline" size={18} color="#B91C1C" />
                            </TouchableOpacity>
                          ) : null}
                        </View>
                      </View>
                      {assigned.length ? assigned.map((unit) => (
                        <DraggableUnit
                          key={unit.key}
                          unit={unit}
                          selected={selectedUnit === unit.key}
                          disabled={saving}
                          onPress={() => setSelectedUnit((current) => current === unit.key ? null : unit.key)}
                          onDragStart={handleDragStart}
                          onDragMove={handleDragMove}
                          onDrop={(x, y) => handleDrop(unit.key, x, y)}
                          onDragFinish={handleDragFinish}
                        />
                      )) : <Text style={styles.zoneEmpty}>{selectedUnit ? 'Toca aqui para mover el producto.' : 'Arrastra productos aqui.'}</Text>}
                    </View>
                  </TouchableOpacity>
                );
              })}

              <TouchableOpacity style={styles.addAccountButton} onPress={addAccount} disabled={saving || accounts.length >= 12}>
                <Ionicons name="add-circle-outline" size={21} color="#2563EB" />
                <Text style={styles.addAccountText}>Agregar otra cuenta</Text>
              </TouchableOpacity>
            </ScrollView>
            <View style={styles.footer}>
              <View>
                <Text style={styles.footerLabel}>Total repartido</Text>
                <Text style={styles.footerTotal}>{money(units.reduce((sum, unit) => sum + unit.price, 0) - unassigned.reduce((sum, unit) => sum + unit.price, 0))}</Text>
              </View>
              <TouchableOpacity
                style={[styles.continueButton, (unassigned.length > 0 || accounts.some((account) => accountUnits(account.key).length === 0)) && styles.buttonDisabled]}
                onPress={createSplit}
                disabled={saving || unassigned.length > 0 || accounts.some((account) => accountUnits(account.key).length === 0)}
              >
                {saving ? <ActivityIndicator color="#FFFFFF" /> : <Text style={styles.continueText}>Continuar al cobro</Text>}
              </TouchableOpacity>
            </View>
          </>
        )}
      </SafeAreaView>
    </Modal>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: '#F4F6F8' },
  header: { height: 68, paddingHorizontal: 16, flexDirection: 'row', alignItems: 'center', borderBottomWidth: 1, borderBottomColor: '#E2E8F0', backgroundColor: '#FFFFFF' },
  iconButton: { width: 42, height: 42, borderRadius: 14, alignItems: 'center', justifyContent: 'center', backgroundColor: '#F1F5F9' },
  headerCopy: { flex: 1, marginHorizontal: 12 },
  title: { fontSize: 20, fontWeight: '800', color: '#111827' },
  subtitle: { marginTop: 2, fontSize: 13, color: '#64748B' },
  countBadge: { minWidth: 40, height: 32, borderRadius: 16, paddingHorizontal: 10, alignItems: 'center', justifyContent: 'center', backgroundColor: '#DBEAFE' },
  countBadgeText: { color: '#1D4ED8', fontWeight: '800' },
  tipBar: { margin: 14, marginBottom: 0, borderRadius: 14, padding: 12, flexDirection: 'row', alignItems: 'center', gap: 9, backgroundColor: '#EFF6FF' },
  tipText: { flex: 1, color: '#1E40AF', fontSize: 13, lineHeight: 18 },
  splitContent: { padding: 14, paddingBottom: 28, gap: 14 },
  zone: { padding: 14, borderRadius: 18, borderWidth: 2, borderColor: '#E2E8F0', backgroundColor: '#FFFFFF' },
  unassignedZone: { borderStyle: 'dashed', backgroundColor: '#FFFDF7' },
  zoneHovered: { borderColor: '#2563EB', backgroundColor: '#EFF6FF' },
  zoneHeader: { minHeight: 42, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginBottom: 10 },
  zoneTitle: { color: '#111827', fontSize: 16, fontWeight: '800' },
  zoneMeta: { marginTop: 2, color: '#64748B', fontSize: 12 },
  zoneTotal: { color: '#111827', fontSize: 17, fontWeight: '800' },
  zoneEmpty: { paddingVertical: 14, textAlign: 'center', color: '#94A3B8', fontSize: 13 },
  unitCard: { marginTop: 8, borderWidth: 1, borderColor: '#E2E8F0', borderRadius: 13, backgroundColor: '#FFFFFF' },
  unitCardSelected: { borderColor: '#2563EB', backgroundColor: '#EFF6FF' },
  unitPressable: { minHeight: 62, paddingHorizontal: 10, flexDirection: 'row', alignItems: 'center', gap: 8 },
  unitImage: { width: 44, height: 44, borderRadius: 11, backgroundColor: '#E2E8F0' },
  unitCopy: { flex: 1 },
  unitName: { color: '#1F2937', fontSize: 14, fontWeight: '700' },
  unitPrice: { marginTop: 2, color: '#64748B', fontSize: 12, fontWeight: '600' },
  accountTitleRow: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  accountNumber: { width: 34, height: 34, borderRadius: 17, alignItems: 'center', justifyContent: 'center', backgroundColor: '#DBEAFE' },
  accountNumberPaid: { backgroundColor: '#16A34A' },
  accountNumberText: { color: '#1D4ED8', fontWeight: '800' },
  accountRight: { alignItems: 'flex-end', gap: 7 },
  addAccountButton: { height: 52, borderRadius: 16, borderWidth: 1, borderColor: '#BFDBFE', flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8, backgroundColor: '#EFF6FF' },
  addAccountText: { color: '#1D4ED8', fontWeight: '800' },
  footer: { padding: 14, borderTopWidth: 1, borderTopColor: '#E2E8F0', flexDirection: 'row', alignItems: 'center', gap: 14, backgroundColor: '#FFFFFF' },
  footerLabel: { color: '#64748B', fontSize: 11, textTransform: 'uppercase', fontWeight: '700' },
  footerTotal: { marginTop: 2, color: '#111827', fontSize: 18, fontWeight: '800' },
  continueButton: { flex: 1, height: 50, borderRadius: 15, alignItems: 'center', justifyContent: 'center', backgroundColor: '#2563EB' },
  continueText: { color: '#FFFFFF', fontWeight: '800' },
  buttonDisabled: { opacity: 0.42 },
  paymentContent: { padding: 16, paddingBottom: 38, gap: 12 },
  progressCard: { padding: 18, borderRadius: 20, backgroundColor: '#111827' },
  progressEyebrow: { color: '#93C5FD', fontSize: 11, fontWeight: '800', letterSpacing: 1 },
  progressTitle: { marginTop: 7, color: '#FFFFFF', fontSize: 22, fontWeight: '800' },
  progressTrack: { marginTop: 14, height: 7, borderRadius: 4, overflow: 'hidden', backgroundColor: '#374151' },
  progressFill: { height: '100%', borderRadius: 4, backgroundColor: '#3B82F6' },
  paymentAccount: { padding: 14, borderRadius: 17, borderWidth: 1, borderColor: '#E2E8F0', flexDirection: 'row', alignItems: 'center', gap: 11, backgroundColor: '#FFFFFF' },
  paymentAccountActive: { borderColor: '#93C5FD', backgroundColor: '#EFF6FF' },
  paymentAccountCopy: { flex: 1 },
  paymentAccountName: { color: '#111827', fontSize: 15, fontWeight: '800' },
  paymentAccountMeta: { marginTop: 3, color: '#64748B', fontSize: 12 },
  paymentAccountTotal: { color: '#111827', fontSize: 17, fontWeight: '800' },
  checkoutCard: { marginTop: 6, padding: 17, borderRadius: 20, backgroundColor: '#FFFFFF' },
  checkoutLabel: { color: '#2563EB', fontSize: 12, fontWeight: '800', textTransform: 'uppercase' },
  checkoutHeading: { marginTop: 7, marginBottom: 14, flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  checkoutName: { color: '#111827', fontSize: 20, fontWeight: '800' },
  checkoutTotal: { color: '#111827', fontSize: 24, fontWeight: '900' },
  checkoutItems: { marginBottom: 8, padding: 12, borderRadius: 12, backgroundColor: '#F8FAFC', gap: 8 },
  checkoutItemRow: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  checkoutItemName: { flex: 1, color: '#475569', fontSize: 13, fontWeight: '600' },
  checkoutItemPrice: { color: '#1F2937', fontSize: 13, fontWeight: '800' },
  paymentOption: { height: 54, marginTop: 8, paddingHorizontal: 14, borderRadius: 14, borderWidth: 1, borderColor: '#E2E8F0', flexDirection: 'row', alignItems: 'center', gap: 11 },
  paymentOptionActive: { borderColor: '#60A5FA', backgroundColor: '#EFF6FF' },
  paymentOptionText: { flex: 1, color: '#1F2937', fontSize: 15, fontWeight: '700' },
  payButton: { height: 54, marginTop: 16, borderRadius: 15, alignItems: 'center', justifyContent: 'center', backgroundColor: '#2563EB' },
  payButtonText: { color: '#FFFFFF', fontSize: 15, fontWeight: '800' },
  cancelSplitButton: { paddingVertical: 14, alignItems: 'center' },
  cancelSplitText: { color: '#B91C1C', fontWeight: '800' },
  lockedText: { paddingVertical: 8, textAlign: 'center', color: '#64748B', fontSize: 12 },
});
