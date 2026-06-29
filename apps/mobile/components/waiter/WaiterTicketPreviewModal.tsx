import React from 'react';
import {
  Alert,
  Modal,
  ScrollView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';

export type WaiterTicketLine = {
  key: string;
  name: string;
  quantity: number;
  unitPrice: number;
  subtotal: number;
  notes?: string | null;
  modifiers?: string[];
};

export type WaiterTicketStatus = 'prebill' | 'paid';

type Props = {
  visible: boolean;
  status: WaiterTicketStatus;
  title?: string;
  tableLabel: string;
  customerName?: string | null;
  waiterName?: string | null;
  accountName?: string | null;
  paymentMethod?: string | null;
  tipAmount?: number;
  lines: WaiterTicketLine[];
  onClose: () => void;
};

function money(value: unknown): string {
  const parsed = Number(value);
  return Number.isFinite(parsed) ? `$${parsed.toFixed(2)}` : '$0.00';
}

function paymentLabel(method?: string | null): string {
  if (method === 'tarjeta') return 'Tarjeta';
  if (method === 'transferencia') return 'Transferencia';
  if (method === 'efectivo') return 'Efectivo';
  return 'Pendiente';
}

export function WaiterTicketPreviewModal({
  visible,
  status,
  title,
  tableLabel,
  customerName,
  waiterName,
  accountName,
  paymentMethod,
  tipAmount = 0,
  lines,
  onClose,
}: Props) {
  const insets = useSafeAreaInsets();
  const subtotal = lines.reduce((sum, line) => sum + Number(line.subtotal || 0), 0);
  const tip = Math.max(0, Number(tipAmount || 0));
  const total = subtotal + tip;
  const itemCount = lines.reduce((sum, line) => sum + Number(line.quantity || 0), 0);
  const isPaid = status === 'paid';

  function handlePrintDemo() {
    Alert.alert(
      'Vista previa lista',
      'Para el demo este ticket ya queda generado en pantalla. El siguiente paso es conectarlo a impresora termica, AirPrint o PDF.'
    );
  }

  return (
    <Modal visible={visible} transparent animationType="slide" onRequestClose={onClose}>
      <View style={styles.overlay}>
        <SafeAreaView style={styles.safe} edges={['left', 'right', 'bottom']}>
          <View style={[styles.sheet, { paddingBottom: 14 + Math.max(insets.bottom, 8) }]}>
            <View style={styles.handle} />
            <View style={styles.header}>
              <View>
                <Text style={styles.kicker}>{isPaid ? 'Ticket pagado' : 'Precuenta'}</Text>
                <Text style={styles.title}>{title || (isPaid ? 'Comprobante de pago' : 'Cuenta por pagar')}</Text>
              </View>
              <TouchableOpacity style={styles.iconButton} onPress={onClose}>
                <Ionicons name="close" size={21} color="#111827" />
              </TouchableOpacity>
            </View>

            <ScrollView style={styles.previewScroll} contentContainerStyle={styles.previewContent} showsVerticalScrollIndicator={false}>
              <View style={styles.paper}>
                <Text style={styles.brand}>AMARE</Text>
                <Text style={styles.ticketType}>{isPaid ? 'TICKET PAGADO' : 'PRECUENTA - NO PAGADA'}</Text>
                <View style={styles.dash} />

                <View style={styles.metaRow}>
                  <Text style={styles.metaLabel}>Mesa</Text>
                  <Text style={styles.metaValue}>{tableLabel}</Text>
                </View>
                {accountName ? (
                  <View style={styles.metaRow}>
                    <Text style={styles.metaLabel}>Cuenta</Text>
                    <Text style={styles.metaValue}>{accountName}</Text>
                  </View>
                ) : null}
                <View style={styles.metaRow}>
                  <Text style={styles.metaLabel}>Comensal</Text>
                  <Text style={styles.metaValue}>{customerName || 'Comensal'}</Text>
                </View>
                {waiterName ? (
                  <View style={styles.metaRow}>
                    <Text style={styles.metaLabel}>Mesero</Text>
                    <Text style={styles.metaValue}>{waiterName}</Text>
                  </View>
                ) : null}
                <View style={styles.metaRow}>
                  <Text style={styles.metaLabel}>Fecha</Text>
                  <Text style={styles.metaValue}>{new Date().toLocaleString('es-MX')}</Text>
                </View>
                <View style={styles.metaRow}>
                  <Text style={styles.metaLabel}>Pago</Text>
                  <Text style={[styles.metaValue, !isPaid && styles.pendingText]}>
                    {isPaid ? paymentLabel(paymentMethod) : 'Pendiente'}
                  </Text>
                </View>

                <View style={styles.dash} />
                {lines.length > 0 ? (
                  lines.map((line) => (
                    <View key={line.key} style={styles.lineBlock}>
                      <View style={styles.lineMain}>
                        <Text style={styles.lineName} numberOfLines={2}>{line.quantity}x {line.name}</Text>
                        <Text style={styles.lineTotal}>{money(line.subtotal)}</Text>
                      </View>
                      <Text style={styles.lineUnit}>{money(line.unitPrice)} c/u</Text>
                      {(line.modifiers ?? []).map((modifier, index) => (
                        <Text key={`${line.key}-mod-${index}`} style={styles.lineDetail} numberOfLines={1}>{modifier}</Text>
                      ))}
                      {line.notes ? <Text style={styles.lineNote} numberOfLines={2}>Nota: {line.notes}</Text> : null}
                    </View>
                  ))
                ) : (
                  <Text style={styles.emptyText}>No hay productos para generar ticket.</Text>
                )}

                <View style={styles.dash} />
                <View style={styles.totalRow}>
                  <Text style={styles.totalLabel}>Productos</Text>
                  <Text style={styles.totalValue}>{itemCount}</Text>
                </View>
                <View style={styles.totalRow}>
                  <Text style={styles.totalLabel}>Subtotal</Text>
                  <Text style={styles.totalValue}>{money(subtotal)}</Text>
                </View>
                {tip > 0 ? (
                  <View style={styles.totalRow}>
                    <Text style={styles.totalLabel}>Propina</Text>
                    <Text style={styles.totalValue}>{money(tip)}</Text>
                  </View>
                ) : null}
                <View style={styles.grandTotalRow}>
                  <Text style={styles.grandTotalLabel}>TOTAL</Text>
                  <Text style={styles.grandTotalValue}>{money(total)}</Text>
                </View>
                {!isPaid ? (
                  <Text style={styles.disclaimer}>Documento informativo. No comprueba pago.</Text>
                ) : (
                  <Text style={styles.disclaimer}>Gracias por tu visita.</Text>
                )}
              </View>
            </ScrollView>

            <View style={styles.actions}>
              <TouchableOpacity style={styles.secondaryButton} onPress={onClose}>
                <Text style={styles.secondaryText}>Cerrar</Text>
              </TouchableOpacity>
              <TouchableOpacity style={styles.primaryButton} onPress={handlePrintDemo}>
                <Ionicons name="print-outline" size={18} color="#FFFFFF" />
                <Text style={styles.primaryText}>Imprimir demo</Text>
              </TouchableOpacity>
            </View>
          </View>
        </SafeAreaView>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  overlay: {
    flex: 1,
    justifyContent: 'flex-end',
    backgroundColor: 'rgba(15, 23, 42, 0.52)',
  },
  safe: {
    flex: 1,
    justifyContent: 'flex-end',
  },
  sheet: {
    maxHeight: '92%',
    borderTopLeftRadius: 26,
    borderTopRightRadius: 26,
    backgroundColor: '#F4F6F8',
    paddingHorizontal: 16,
    paddingTop: 10,
  },
  handle: {
    alignSelf: 'center',
    width: 42,
    height: 5,
    borderRadius: 999,
    backgroundColor: '#CBD5E1',
    marginBottom: 12,
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 12,
  },
  kicker: {
    color: '#2563EB',
    fontSize: 11,
    fontWeight: '900',
    textTransform: 'uppercase',
  },
  title: {
    marginTop: 2,
    color: '#111827',
    fontSize: 21,
    fontWeight: '900',
  },
  iconButton: {
    width: 42,
    height: 42,
    borderRadius: 14,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#FFFFFF',
    borderWidth: 1,
    borderColor: '#E5E7EB',
  },
  previewScroll: {
    marginTop: 12,
  },
  previewContent: {
    paddingBottom: 12,
  },
  paper: {
    alignSelf: 'center',
    width: '100%',
    maxWidth: 380,
    borderRadius: 8,
    backgroundColor: '#FFFFFF',
    paddingHorizontal: 18,
    paddingVertical: 18,
    borderWidth: 1,
    borderColor: '#E5E7EB',
  },
  brand: {
    textAlign: 'center',
    color: '#111827',
    fontSize: 24,
    fontWeight: '900',
    letterSpacing: 1,
  },
  ticketType: {
    marginTop: 4,
    textAlign: 'center',
    color: '#64748B',
    fontSize: 12,
    fontWeight: '900',
  },
  dash: {
    marginVertical: 13,
    borderTopWidth: 1,
    borderStyle: 'dashed',
    borderColor: '#CBD5E1',
  },
  metaRow: {
    minHeight: 24,
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: 12,
  },
  metaLabel: {
    color: '#64748B',
    fontSize: 12,
    fontWeight: '800',
  },
  metaValue: {
    flex: 1,
    textAlign: 'right',
    color: '#111827',
    fontSize: 12,
    fontWeight: '900',
  },
  pendingText: {
    color: '#B45309',
  },
  lineBlock: {
    paddingVertical: 8,
  },
  lineMain: {
    flexDirection: 'row',
    gap: 10,
  },
  lineName: {
    flex: 1,
    color: '#111827',
    fontSize: 13,
    fontWeight: '900',
  },
  lineTotal: {
    color: '#111827',
    fontSize: 13,
    fontWeight: '900',
  },
  lineUnit: {
    marginTop: 2,
    color: '#64748B',
    fontSize: 11,
    fontWeight: '700',
  },
  lineDetail: {
    marginTop: 2,
    color: '#475569',
    fontSize: 11,
    fontWeight: '700',
  },
  lineNote: {
    marginTop: 4,
    color: '#92400E',
    fontSize: 11,
    fontWeight: '800',
  },
  emptyText: {
    textAlign: 'center',
    color: '#94A3B8',
    fontWeight: '800',
  },
  totalRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
  },
  totalLabel: {
    color: '#64748B',
    fontSize: 12,
    fontWeight: '900',
  },
  totalValue: {
    color: '#111827',
    fontSize: 12,
    fontWeight: '900',
  },
  grandTotalRow: {
    marginTop: 8,
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'baseline',
  },
  grandTotalLabel: {
    color: '#111827',
    fontSize: 16,
    fontWeight: '900',
  },
  grandTotalValue: {
    color: '#111827',
    fontSize: 25,
    fontWeight: '900',
  },
  disclaimer: {
    marginTop: 14,
    textAlign: 'center',
    color: '#64748B',
    fontSize: 11,
    fontWeight: '800',
  },
  actions: {
    flexDirection: 'row',
    gap: 10,
    paddingTop: 4,
  },
  secondaryButton: {
    flex: 1,
    minHeight: 52,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: '#CBD5E1',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#FFFFFF',
  },
  secondaryText: {
    color: '#111827',
    fontWeight: '900',
  },
  primaryButton: {
    flex: 1.3,
    minHeight: 52,
    borderRadius: 16,
    backgroundColor: '#111827',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
  },
  primaryText: {
    color: '#FFFFFF',
    fontWeight: '900',
  },
});
