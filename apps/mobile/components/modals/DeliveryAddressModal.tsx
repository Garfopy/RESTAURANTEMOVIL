import React, { useEffect, useMemo, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  KeyboardAvoidingView,
  Modal,
  Platform,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import MapView, { Region } from 'react-native-maps';
import * as Location from 'expo-location';
import { apiClient, getApiError } from '../../services/api';
import { Button } from '../ui/Button';
import { FormField } from '../ui/FormField';
import { Colors, Spacing } from '../../theme';
import type { DeliveryAddressSelection } from '../../store/cart.store';

type SavedAddress = {
  id: number | string;
  alias?: string | null;
  calle?: string | null;
  numero?: string | null;
  colonia?: string | null;
  ciudad?: string | null;
  cp?: string | null;
  lat?: number | string | null;
  lng?: number | string | null;
  instrucciones?: string | null;
  es_principal?: boolean;
};

type DraftAddress = {
  calle: string;
  colonia: string;
  ciudad: string;
  cp: string;
  lat: number;
  lng: number;
};

type DeliveryAddressModalProps = {
  visible: boolean;
  onDismiss: () => void;
  onConfirm: (address: DeliveryAddressSelection) => void;
};

const DEFAULT_REGION: Region = {
  latitude: 20.591403,
  longitude: -100.396631,
  latitudeDelta: 0.006,
  longitudeDelta: 0.006,
};

const ALIAS_OPTIONS = ['Casa', 'Trabajo', 'Otro'];

export function DeliveryAddressModal({ visible, onDismiss, onConfirm }: DeliveryAddressModalProps) {
  const insets = useSafeAreaInsets();
  const [addresses, setAddresses] = useState<SavedAddress[]>([]);
  const [selectedAddressId, setSelectedAddressId] = useState<number | string | null>(null);
  const [mode, setMode] = useState<'saved' | 'new'>('saved');
  const [loadingAddresses, setLoadingAddresses] = useState(false);
  const [loadingLocation, setLoadingLocation] = useState(false);
  const [saving, setSaving] = useState(false);
  const [region, setRegion] = useState<Region>(DEFAULT_REGION);
  const [draft, setDraft] = useState<DraftAddress | null>(null);
  const [alias, setAlias] = useState('Casa');
  const [customAlias, setCustomAlias] = useState('');
  const [numero, setNumero] = useState('');
  const [instrucciones, setInstrucciones] = useState('');

  const selectedAddress = useMemo(
    () => addresses.find((address) => String(address.id) === String(selectedAddressId)) ?? null,
    [addresses, selectedAddressId]
  );

  useEffect(() => {
    if (!visible) return;

    resetNewAddressFields();
    void loadAddresses();
  }, [visible]);

  async function loadAddresses() {
    try {
      setLoadingAddresses(true);
      const response = await apiClient.get('/profile/addresses');
      const rows = Array.isArray(response.data?.data) ? response.data.data : [];
      setAddresses(rows);

      const principal = rows.find((address: SavedAddress) => address.es_principal) ?? rows[0] ?? null;
      setSelectedAddressId(principal?.id ?? null);
      setMode(rows.length > 0 ? 'saved' : 'new');

      if (rows.length === 0) {
        await startNewAddress();
      }
    } catch (error) {
      console.error('Error cargando direcciones:', error);
      setMode('new');
      await startNewAddress();
    } finally {
      setLoadingAddresses(false);
    }
  }

  function resetNewAddressFields() {
    setRegion(DEFAULT_REGION);
    setDraft(null);
    setAlias('Casa');
    setCustomAlias('');
    setNumero('');
    setInstrucciones('');
  }

  async function startNewAddress() {
    setMode('new');
    setSelectedAddressId(null);

    try {
      setLoadingLocation(true);
      const permission = await Location.requestForegroundPermissionsAsync();

      if (permission.status !== 'granted') {
        Alert.alert(
          'Ubicación',
          'No pudimos usar tu GPS. Puedes mover el mapa manualmente y completar la dirección.'
        );
        await updateAddressFromCoords(DEFAULT_REGION.latitude, DEFAULT_REGION.longitude);
        return;
      }

      const current = await Location.getCurrentPositionAsync({
        accuracy: Location.Accuracy.Balanced,
      });
      const nextRegion = {
        latitude: current.coords.latitude,
        longitude: current.coords.longitude,
        latitudeDelta: 0.006,
        longitudeDelta: 0.006,
      };
      setRegion(nextRegion);
      await updateAddressFromCoords(nextRegion.latitude, nextRegion.longitude);
    } catch (error) {
      console.error('Error obteniendo ubicación:', error);
      await updateAddressFromCoords(DEFAULT_REGION.latitude, DEFAULT_REGION.longitude);
    } finally {
      setLoadingLocation(false);
    }
  }

  async function updateAddressFromCoords(lat: number, lng: number) {
    try {
      const reverse = await Location.reverseGeocodeAsync({ latitude: lat, longitude: lng });
      const address = reverse[0];
      const calle = [address?.street, address?.name].filter(Boolean).join(' ').trim() || '';
      const colonia = address?.district || address?.subregion || '';
      const ciudad = address?.city || address?.subregion || '';

      setDraft({
        calle,
        colonia,
        ciudad,
        cp: address?.postalCode || '',
        lat,
        lng,
      });
    } catch {
      setDraft((current) => ({
        calle: current?.calle ?? '',
        colonia: current?.colonia ?? '',
        ciudad: current?.ciudad ?? '',
        cp: current?.cp ?? '',
        lat,
        lng,
      }));
    }
  }

  function handleRegionChangeComplete(nextRegion: Region) {
    setRegion(nextRegion);
    void updateAddressFromCoords(nextRegion.latitude, nextRegion.longitude);
  }

  function formatSavedAddress(address: SavedAddress): string {
    const street = [address.calle, address.numero].filter(Boolean).join(' ');
    return [street, address.colonia, address.ciudad].filter(Boolean).join(', ');
  }

  function buildSelection(address: SavedAddress): DeliveryAddressSelection {
    return {
      id: address.id,
      alias: address.alias ?? null,
      text: formatSavedAddress(address),
      lat: address.lat == null ? null : Number(address.lat),
      lng: address.lng == null ? null : Number(address.lng),
      instrucciones: address.instrucciones ?? null,
    };
  }

  function handleConfirmSavedAddress() {
    if (!selectedAddress) {
      Alert.alert('Dirección requerida', 'Selecciona una dirección o agrega una nueva.');
      return;
    }

    onConfirm(buildSelection(selectedAddress));
  }

  async function handleSaveNewAddress() {
    if (!draft) {
      Alert.alert('Ubicación requerida', 'Mueve el pin en el mapa para confirmar tu ubicación.');
      return;
    }

    const finalAlias = alias === 'Otro' ? customAlias.trim() || 'Otro' : alias;

    if (!draft.calle.trim()) {
      Alert.alert('Dirección incompleta', 'Agrega la calle o avenida de entrega.');
      return;
    }
    if (!draft.ciudad.trim()) {
      Alert.alert('Dirección incompleta', 'Agrega la ciudad o municipio.');
      return;
    }

    try {
      setSaving(true);
      const response = await apiClient.post('/profile/addresses', {
        alias: finalAlias,
        calle: draft.calle.trim(),
        numero: numero.trim() || null,
        colonia: draft.colonia.trim() || null,
        ciudad: draft.ciudad.trim(),
        cp: draft.cp.trim() || null,
        lat: draft.lat,
        lng: draft.lng,
        instrucciones: instrucciones.trim() || null,
        es_principal: addresses.length === 0,
      });

      const saved = response.data?.data as SavedAddress | undefined;
      if (!saved) {
        throw new Error('No se recibió la dirección guardada.');
      }

      onConfirm(buildSelection(saved));
    } catch (error) {
      Alert.alert('No se pudo guardar', getApiError(error));
    } finally {
      setSaving(false);
    }
  }

  return (
    <Modal
      visible={visible}
      animationType="slide"
      presentationStyle={Platform.OS === 'ios' ? 'pageSheet' : 'fullScreen'}
      onRequestClose={onDismiss}
    >
      <SafeAreaView style={styles.safe} edges={['top', 'left', 'right']}>
        <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : 'height'} style={styles.flex}>
          <View style={styles.header}>
            <TouchableOpacity onPress={onDismiss} style={styles.closeButton} activeOpacity={0.8}>
              <Ionicons name="close" size={24} color={Colors.textSecondary} />
            </TouchableOpacity>
            <Text style={styles.headerTitle}>Dirección de entrega</Text>
            <View style={styles.closeButton} />
          </View>

          {loadingAddresses ? (
            <View style={styles.loadingWrap}>
              <ActivityIndicator color={Colors.primary} />
              <Text style={styles.loadingText}>Cargando tus direcciones...</Text>
            </View>
          ) : (
            <>
              <ScrollView contentContainerStyle={styles.content} showsVerticalScrollIndicator={false} keyboardShouldPersistTaps="handled">
                {addresses.length > 0 ? (
                  <View style={styles.modeSwitch}>
                    <TouchableOpacity
                      style={[styles.modeButton, mode === 'saved' && styles.modeButtonActive]}
                      onPress={() => setMode('saved')}
                      activeOpacity={0.82}
                    >
                      <Ionicons name="bookmarks-outline" size={17} color={mode === 'saved' ? '#FFF' : Colors.primary} />
                      <Text style={[styles.modeButtonText, mode === 'saved' && styles.modeButtonTextActive]}>Guardadas</Text>
                    </TouchableOpacity>
                    <TouchableOpacity
                      style={[styles.modeButton, mode === 'new' && styles.modeButtonActive]}
                      onPress={() => {
                        resetNewAddressFields();
                        void startNewAddress();
                      }}
                      activeOpacity={0.82}
                    >
                      <Ionicons name="add" size={18} color={mode === 'new' ? '#FFF' : Colors.primary} />
                      <Text style={[styles.modeButtonText, mode === 'new' && styles.modeButtonTextActive]}>Nueva</Text>
                    </TouchableOpacity>
                  </View>
                ) : null}

                {mode === 'saved' && addresses.length > 0 ? (
                  <View style={styles.addressList}>
                    {addresses.map((address) => {
                      const active = String(address.id) === String(selectedAddressId);
                      return (
                        <TouchableOpacity
                          key={address.id}
                          style={[styles.addressCard, active && styles.addressCardActive]}
                          onPress={() => setSelectedAddressId(address.id)}
                          activeOpacity={0.86}
                        >
                          <View style={[styles.addressIcon, active && styles.addressIconActive]}>
                            <Ionicons name="home-outline" size={20} color={active ? '#FFF' : Colors.primary} />
                          </View>
                          <View style={styles.addressCopy}>
                            <Text style={styles.addressAlias}>{address.alias || 'Dirección'}</Text>
                            <Text style={styles.addressText} numberOfLines={2}>{formatSavedAddress(address)}</Text>
                            {address.instrucciones ? (
                              <Text style={styles.addressHint} numberOfLines={1}>{address.instrucciones}</Text>
                            ) : null}
                          </View>
                          <Ionicons
                            name={active ? 'checkmark-circle' : 'ellipse-outline'}
                            size={22}
                            color={active ? Colors.primary : '#CBD5E1'}
                          />
                        </TouchableOpacity>
                      );
                    })}
                  </View>
                ) : null}

                {mode === 'new' ? (
                  <View style={styles.newAddressWrap}>
                    <View style={styles.mapWrapper}>
                      <MapView
                        style={styles.map}
                        region={region}
                        onRegionChangeComplete={handleRegionChangeComplete}
                        showsUserLocation
                        rotateEnabled={false}
                        pitchEnabled={false}
                        toolbarEnabled={false}
                      />
                      <View style={styles.mapCenterPointer} pointerEvents="none">
                        <Ionicons name="location" size={42} color={Colors.primary} style={{ marginTop: -42 }} />
                        <View style={styles.pointerShadow} />
                      </View>
                      {loadingLocation ? (
                        <View style={styles.mapLoading}>
                          <ActivityIndicator color={Colors.primary} />
                          <Text style={styles.mapLoadingText}>Detectando ubicación...</Text>
                        </View>
                      ) : null}
                    </View>

                    <Text style={styles.helperText}>Mueve el mapa hasta dejar el pin sobre tu entrada.</Text>

                    <View style={styles.aliasRow}>
                      {ALIAS_OPTIONS.map((item) => {
                        const active = alias === item;
                        return (
                          <TouchableOpacity
                            key={item}
                            style={[styles.aliasButton, active && styles.aliasButtonActive]}
                            onPress={() => setAlias(item)}
                            activeOpacity={0.82}
                          >
                            <Text style={[styles.aliasButtonText, active && styles.aliasButtonTextActive]}>{item}</Text>
                          </TouchableOpacity>
                        );
                      })}
                    </View>

                    {alias === 'Otro' ? (
                      <FormField
                        label="Nombre"
                        value={customAlias}
                        onChangeText={setCustomAlias}
                        placeholder="Ej: Oficina, departamento"
                        icon="bookmark-outline"
                      />
                    ) : null}

                    <FormField
                      label="Calle o avenida"
                      value={draft?.calle ?? ''}
                      onChangeText={(text) => setDraft((current) => ({ ...(current ?? defaultDraft()), calle: text }))}
                      placeholder="Ej: Avenida Universidad"
                      icon="map-outline"
                    />

                    <View style={styles.formRow}>
                      <View style={styles.formColumn}>
                        <FormField
                          label="Número"
                          value={numero}
                          onChangeText={setNumero}
                          placeholder="Ej: 405-B"
                          icon="business-outline"
                        />
                      </View>
                      <View style={styles.formColumn}>
                        <FormField
                          label="CP"
                          value={draft?.cp ?? ''}
                          onChangeText={(text) => setDraft((current) => ({ ...(current ?? defaultDraft()), cp: text }))}
                          placeholder="76000"
                          keyboardType="numeric"
                          icon="mail-open-outline"
                        />
                      </View>
                    </View>

                    <FormField
                      label="Colonia"
                      value={draft?.colonia ?? ''}
                      onChangeText={(text) => setDraft((current) => ({ ...(current ?? defaultDraft()), colonia: text }))}
                      placeholder="Ej: Centro"
                      icon="navigate-outline"
                    />

                    <FormField
                      label="Ciudad"
                      value={draft?.ciudad ?? ''}
                      onChangeText={(text) => setDraft((current) => ({ ...(current ?? defaultDraft()), ciudad: text }))}
                      placeholder="Ej: Querétaro"
                      icon="location-outline"
                    />

                    <View style={styles.notesField}>
                      <Text style={styles.notesLabel}>Referencias y senas</Text>
                      <TextInput
                        value={instrucciones}
                        onChangeText={setInstrucciones}
                        placeholder="Color de fachada, entre calles, porton, piso, instrucciones para llegar..."
                        placeholderTextColor="#9CA3AF"
                        multiline
                        textAlignVertical="top"
                        style={styles.notesInput}
                      />
                    </View>
                  </View>
                ) : null}
              </ScrollView>

              <View style={[styles.footer, { paddingBottom: (Spacing.base || 16) + Math.max(insets.bottom, Platform.OS === 'android' ? 8 : 0) }]}>
                <Button
                  label={mode === 'saved' ? 'Usar esta dirección' : 'Guardar y usar dirección'}
                  onPress={mode === 'saved' ? handleConfirmSavedAddress : handleSaveNewAddress}
                  loading={saving}
                  disabled={saving || loadingLocation}
                  fullWidth
                  size="lg"
                />
              </View>
            </>
          )}
        </KeyboardAvoidingView>
      </SafeAreaView>
    </Modal>
  );

  function defaultDraft(): DraftAddress {
    return {
      calle: '',
      colonia: '',
      ciudad: '',
      cp: '',
      lat: region.latitude,
      lng: region.longitude,
    };
  }
}

const styles = StyleSheet.create({
  safe: {
    flex: 1,
    backgroundColor: '#FFFFFF',
  },
  flex: {
    flex: 1,
  },
  header: {
    minHeight: 58,
    paddingHorizontal: Spacing.base || 16,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    borderBottomWidth: 1,
    borderBottomColor: '#F3F4F6',
  },
  closeButton: {
    width: 40,
    height: 40,
    borderRadius: 20,
    alignItems: 'center',
    justifyContent: 'center',
  },
  headerTitle: {
    fontSize: 18,
    fontWeight: '800',
    color: '#111827',
  },
  loadingWrap: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 10,
  },
  loadingText: {
    fontSize: 14,
    color: '#6B7280',
  },
  content: {
    padding: Spacing.base || 16,
    paddingBottom: 24,
    gap: 16,
  },
  modeSwitch: {
    minHeight: 48,
    padding: 4,
    borderRadius: 16,
    backgroundColor: '#F3F4F6',
    flexDirection: 'row',
    gap: 4,
  },
  modeButton: {
    flex: 1,
    borderRadius: 13,
    alignItems: 'center',
    justifyContent: 'center',
    flexDirection: 'row',
    gap: 7,
  },
  modeButtonActive: {
    backgroundColor: Colors.primary,
  },
  modeButtonText: {
    fontSize: 14,
    fontWeight: '800',
    color: Colors.primary,
  },
  modeButtonTextActive: {
    color: '#FFFFFF',
  },
  addressList: {
    gap: 10,
  },
  addressCard: {
    minHeight: 92,
    borderRadius: 18,
    padding: 14,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    backgroundColor: '#FFFFFF',
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  addressCardActive: {
    borderColor: Colors.primary,
    backgroundColor: '#FFF7F8',
  },
  addressIcon: {
    width: 42,
    height: 42,
    borderRadius: 21,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#F3F4F6',
  },
  addressIconActive: {
    backgroundColor: Colors.primary,
  },
  addressCopy: {
    flex: 1,
    minWidth: 0,
  },
  addressAlias: {
    fontSize: 15,
    fontWeight: '800',
    color: '#111827',
  },
  addressText: {
    marginTop: 3,
    fontSize: 13,
    lineHeight: 18,
    color: '#4B5563',
  },
  addressHint: {
    marginTop: 3,
    fontSize: 12,
    color: '#9CA3AF',
  },
  newAddressWrap: {
    gap: 14,
  },
  mapWrapper: {
    height: 240,
    borderRadius: 18,
    overflow: 'hidden',
    borderWidth: 1,
    borderColor: '#E5E7EB',
    backgroundColor: '#F3F4F6',
  },
  map: {
    flex: 1,
  },
  mapCenterPointer: {
    position: 'absolute',
    top: '50%',
    left: '50%',
    marginLeft: -21,
    marginTop: -21,
    alignItems: 'center',
    justifyContent: 'center',
  },
  pointerShadow: {
    width: 6,
    height: 6,
    borderRadius: 3,
    backgroundColor: 'rgba(0,0,0,0.22)',
    marginTop: -4,
  },
  mapLoading: {
    position: 'absolute',
    left: 12,
    right: 12,
    bottom: 12,
    borderRadius: 14,
    padding: 10,
    backgroundColor: 'rgba(255,255,255,0.95)',
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  mapLoadingText: {
    fontSize: 13,
    fontWeight: '700',
    color: '#4B5563',
  },
  helperText: {
    marginTop: -4,
    fontSize: 13,
    lineHeight: 18,
    color: '#6B7280',
  },
  aliasRow: {
    flexDirection: 'row',
    gap: 8,
  },
  aliasButton: {
    flex: 1,
    minHeight: 44,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    backgroundColor: '#FFFFFF',
    alignItems: 'center',
    justifyContent: 'center',
  },
  aliasButtonActive: {
    borderColor: Colors.primary,
    backgroundColor: Colors.primary,
  },
  aliasButtonText: {
    fontSize: 14,
    fontWeight: '800',
    color: '#4B5563',
  },
  aliasButtonTextActive: {
    color: '#FFFFFF',
  },
  formRow: {
    flexDirection: 'row',
    gap: 12,
  },
  formColumn: {
    flex: 1,
  },
  notesField: {
    gap: 8,
  },
  notesLabel: {
    fontSize: 14,
    fontWeight: '700',
    color: '#111827',
    marginLeft: 4,
  },
  notesInput: {
    minHeight: 94,
    borderWidth: 1.5,
    borderColor: '#E5E7EB',
    borderRadius: 12,
    paddingHorizontal: 14,
    paddingVertical: 12,
    fontSize: 15,
    color: '#111827',
    backgroundColor: '#FFFFFF',
  },
  footer: {
    padding: Spacing.base || 16,
    borderTopWidth: 1,
    borderTopColor: '#F3F4F6',
    backgroundColor: '#FFFFFF',
  },
});
