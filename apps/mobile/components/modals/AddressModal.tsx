import React, { useEffect, useMemo, useState } from 'react';
import {
  ActivityIndicator,
  KeyboardAvoidingView,
  Modal,
  Platform,
  ScrollView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import * as Location from 'expo-location';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '../../services/api';
import { FormField } from '../ui/FormField';
import { Button } from '../ui/Button';
import { useToast } from '../../context/ToastContext';
import { mapErrorToFriendly } from '../../services/error.service';
import { Colors, Spacing } from '../../theme';
import type { Direccion } from '@amare/types';

interface AddressModalProps {
  visible: boolean;
  onDismiss: () => void;
  address?: Direccion | null;
  onSuccess?: (address: Direccion) => void;
}

const ALIAS_PRESETS = [
  { id: 'Casa', label: 'Casa', iconOutline: 'home-outline', iconFilled: 'home' },
  { id: 'Trabajo', label: 'Trabajo', iconOutline: 'briefcase-outline', iconFilled: 'briefcase' },
  { id: 'Otro', label: 'Otro', iconOutline: 'ellipsis-horizontal-outline', iconFilled: 'ellipsis-horizontal' },
];

const STREET_HINTS = [
  { calle: 'Avenida Universidad', colonia: '', ciudad: 'Queretaro', cp: '76000' },
  { calle: 'Avenida Constituyentes', colonia: '', ciudad: 'Queretaro', cp: '76000' },
  { calle: 'Boulevard Bernardo Quintana', colonia: '', ciudad: 'Queretaro', cp: '76010' },
  { calle: 'Avenida 5 de Febrero', colonia: '', ciudad: 'Queretaro', cp: '76120' },
  { calle: 'Prolongacion Corregidora Norte', colonia: '', ciudad: 'Queretaro', cp: '76140' },
  { calle: 'Avenida Tecnologico', colonia: '', ciudad: 'Queretaro', cp: '76030' },
];

type AddressSuggestion = {
  id: string;
  label: string;
  detail: string;
  icon: keyof typeof Ionicons.glyphMap;
  calle: string;
  colonia?: string | null;
  ciudad?: string | null;
  cp?: string | null;
  lat?: number | null;
  lng?: number | null;
};

function normalizeText(value?: string | number | null): string {
  return String(value ?? '')
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .trim();
}

function composeDetail(address: Partial<Direccion>): string {
  return [address.colonia, address.ciudad, address.cp ? `CP ${address.cp}` : null]
    .filter(Boolean)
    .join(' - ');
}

function coordinates(value: unknown): number | null {
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : null;
}

export function AddressModal({
  visible,
  onDismiss,
  address,
  onSuccess,
}: AddressModalProps) {
  const insets = useSafeAreaInsets();
  const toast = useToast();
  const qc = useQueryClient();

  const [alias, setAlias] = useState('');
  const [isCustomAlias, setIsCustomAlias] = useState(false);
  const [calle, setCalle] = useState('');
  const [numero, setNumero] = useState('');
  const [colonia, setColonia] = useState('');
  const [ciudad, setCiudad] = useState('');
  const [cp, setCp] = useState('');
  const [instrucciones, setInstrucciones] = useState('');
  const [lat, setLat] = useState<number | null>(null);
  const [lng, setLng] = useState<number | null>(null);

  const [aliasError, setAliasError] = useState<string | null>(null);
  const [calleError, setCalleError] = useState<string | null>(null);
  const [ciudadError, setCiudadError] = useState<string | null>(null);

  const [savedAddressHints, setSavedAddressHints] = useState<Direccion[]>([]);
  const [geoSuggestions, setGeoSuggestions] = useState<AddressSuggestion[]>([]);
  const [suggestionsLoading, setSuggestionsLoading] = useState(false);
  const [streetFocused, setStreetFocused] = useState(false);

  useEffect(() => {
    if (address) {
      const isPreset = ALIAS_PRESETS.some((preset) => preset.id === address.alias);
      setAlias(address.alias || '');
      setIsCustomAlias(!isPreset && !!address.alias);
      setCalle(address.calle || '');
      setNumero(address.numero || '');
      setColonia(address.colonia || '');
      setCiudad(address.ciudad || '');
      setCp(address.cp || '');
      setInstrucciones(address.instrucciones || '');
      setLat(coordinates(address.lat));
      setLng(coordinates(address.lng));
    } else if (visible) {
      resetForm();
    }
  }, [visible, address]);

  useEffect(() => {
    if (!visible) return;

    let cancelled = false;
    async function loadAddressHints() {
      try {
        const response = await apiClient.get('/profile/addresses');
        const rows = Array.isArray(response.data?.data) ? response.data.data : [];
        if (!cancelled) setSavedAddressHints(rows);
      } catch {
        if (!cancelled) setSavedAddressHints([]);
      }
    }

    void loadAddressHints();
    return () => {
      cancelled = true;
    };
  }, [visible]);

  useEffect(() => {
    const query = calle.trim();
    if (!visible || query.length < 4) {
      setGeoSuggestions([]);
      setSuggestionsLoading(false);
      return;
    }

    let cancelled = false;
    const timer = setTimeout(async () => {
      try {
        setSuggestionsLoading(true);
        const search = [query, colonia, ciudad || 'Queretaro', 'Mexico']
          .filter((part) => String(part ?? '').trim())
          .join(', ');
        const locations = await Location.geocodeAsync(search);

        const next = await Promise.all(
          locations.slice(0, 3).map(async (location, index): Promise<AddressSuggestion> => {
            try {
              const reverse = await Location.reverseGeocodeAsync({
                latitude: location.latitude,
                longitude: location.longitude,
              });
              const found = reverse[0];
              const foundStreet = [found?.street, found?.name].filter(Boolean).join(' ').trim() || query;
              const foundColony = found?.district || found?.subregion || '';
              const foundCity = found?.city || found?.subregion || ciudad || '';
              const foundCp = found?.postalCode || '';

              return {
                id: `geo-${index}-${location.latitude}-${location.longitude}`,
                label: foundStreet,
                detail: [foundColony, foundCity, foundCp ? `CP ${foundCp}` : null].filter(Boolean).join(' - '),
                icon: 'location-outline',
                calle: foundStreet,
                colonia: foundColony,
                ciudad: foundCity,
                cp: foundCp,
                lat: location.latitude,
                lng: location.longitude,
              };
            } catch {
              return {
                id: `geo-${index}-${location.latitude}-${location.longitude}`,
                label: query,
                detail: 'Ubicacion encontrada',
                icon: 'location-outline',
                calle: query,
                colonia,
                ciudad,
                cp,
                lat: location.latitude,
                lng: location.longitude,
              };
            }
          })
        );

        if (!cancelled) setGeoSuggestions(next);
      } catch {
        if (!cancelled) setGeoSuggestions([]);
      } finally {
        if (!cancelled) setSuggestionsLoading(false);
      }
    }, 450);

    return () => {
      cancelled = true;
      clearTimeout(timer);
    };
  }, [calle, ciudad, colonia, cp, visible]);

  const addressSuggestions = useMemo(() => {
    const query = normalizeText(calle);
    if (query.length < 2) return [];

    const fromSaved = savedAddressHints
      .filter((item) => item.id !== address?.id)
      .filter((item) =>
        [item.calle, item.colonia, item.ciudad, item.cp].some((value) => normalizeText(value).includes(query))
      )
      .slice(0, 3)
      .map<AddressSuggestion>((item) => ({
        id: `saved-${item.id}`,
        label: [item.calle, item.numero].filter(Boolean).join(' '),
        detail: composeDetail(item),
        icon: 'time-outline',
        calle: item.calle,
        colonia: item.colonia,
        ciudad: item.ciudad,
        cp: item.cp,
        lat: coordinates(item.lat),
        lng: coordinates(item.lng),
      }));

    const fromHints = STREET_HINTS
      .filter((item) => normalizeText(item.calle).includes(query))
      .slice(0, 3)
      .map<AddressSuggestion>((item) => ({
        id: `hint-${item.calle}`,
        label: item.calle,
        detail: [item.colonia, item.ciudad, item.cp ? `CP ${item.cp}` : null].filter(Boolean).join(' - '),
        icon: 'map-outline',
        calle: item.calle,
        colonia: item.colonia,
        ciudad: item.ciudad,
        cp: item.cp,
      }));

    const seen = new Set<string>();
    return [...geoSuggestions, ...fromSaved, ...fromHints]
      .filter((item) => {
        const key = normalizeText(`${item.label}-${item.detail}`);
        if (seen.has(key)) return false;
        seen.add(key);
        return true;
      })
      .slice(0, 5);
  }, [address?.id, calle, geoSuggestions, savedAddressHints]);

  const saveMutation = useMutation({
    mutationFn: async (data: any) => {
      if (address) {
        const { data: res } = await apiClient.put(`/profile/addresses/${address.id}`, data);
        return res.data;
      }

      const { data: res } = await apiClient.post('/profile/addresses', data);
      return res.data;
    },
    onSuccess: (savedAddress) => {
      qc.invalidateQueries({ queryKey: ['addresses'] });
      toast.success(address ? 'Direccion actualizada' : 'Direccion guardada');
      onSuccess?.(savedAddress);
      resetForm();
      onDismiss();
    },
    onError: (err) => {
      const friendlyError = mapErrorToFriendly(err);
      toast.error(friendlyError.message);
    },
  });

  function resetForm() {
    setAlias('');
    setIsCustomAlias(false);
    setCalle('');
    setNumero('');
    setColonia('');
    setCiudad('');
    setCp('');
    setInstrucciones('');
    setLat(null);
    setLng(null);
    setAliasError(null);
    setCalleError(null);
    setCiudadError(null);
    setGeoSuggestions([]);
    setStreetFocused(false);
  }

  function validateForm() {
    let valid = true;

    if (!alias.trim()) {
      setAliasError('El alias o tipo es requerido');
      valid = false;
    } else if (alias.trim().length < 2) {
      setAliasError('Debe tener al menos 2 caracteres');
      valid = false;
    } else {
      setAliasError(null);
    }

    if (!calle.trim()) {
      setCalleError('La calle es requerida');
      valid = false;
    } else if (calle.trim().length < 3) {
      setCalleError('Debe tener al menos 3 caracteres');
      valid = false;
    } else {
      setCalleError(null);
    }

    if (!ciudad.trim()) {
      setCiudadError('La ciudad es requerida');
      valid = false;
    } else if (ciudad.trim().length < 2) {
      setCiudadError('Debe tener al menos 2 caracteres');
      valid = false;
    } else {
      setCiudadError(null);
    }

    return valid;
  }

  function handleSave() {
    if (!validateForm()) {
      toast.error('Por favor, completa los campos requeridos');
      return;
    }

    saveMutation.mutate({
      alias: alias.trim(),
      calle: calle.trim(),
      numero: numero.trim() || null,
      colonia: colonia.trim() || null,
      ciudad: ciudad.trim(),
      cp: cp.trim() || null,
      lat,
      lng,
      instrucciones: instrucciones.trim() || null,
      es_principal: address?.es_principal ?? false,
    });
  }

  function handlePresetPress(presetId: string) {
    setAliasError(null);
    if (presetId === 'Otro') {
      setAlias('');
      setIsCustomAlias(true);
      return;
    }

    setAlias(presetId);
    setIsCustomAlias(false);
  }

  function handleSuggestionPress(suggestion: AddressSuggestion) {
    setCalle(suggestion.calle);
    setColonia(suggestion.colonia ?? colonia);
    setCiudad(suggestion.ciudad ?? ciudad);
    setCp(suggestion.cp ?? cp);
    setLat(suggestion.lat ?? null);
    setLng(suggestion.lng ?? null);
    setCalleError(null);
    setCiudadError(null);
    setStreetFocused(false);
  }

  const showSuggestions = streetFocused && (calle.trim().length >= 2 || suggestionsLoading);

  return (
    <Modal
      visible={visible}
      animationType="slide"
      presentationStyle={Platform.OS === 'ios' ? 'pageSheet' : 'fullScreen'}
      onRequestClose={onDismiss}
    >
      <SafeAreaView style={styles.safe} edges={['top', 'left', 'right']}>
        <KeyboardAvoidingView
          behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
          style={styles.flex}
        >
          <View style={styles.header}>
            <TouchableOpacity
              style={styles.closeButton}
              onPress={onDismiss}
              hitSlop={{ top: 15, bottom: 15, left: 15, right: 15 }}
            >
              <Ionicons name="close-outline" size={26} color={Colors.text} />
            </TouchableOpacity>
            <Text style={styles.headerTitle}>{address ? 'Editar direccion' : 'Nueva direccion'}</Text>
            <View style={styles.closeButton} />
          </View>

          <ScrollView
            contentContainerStyle={styles.content}
            keyboardShouldPersistTaps="handled"
            showsVerticalScrollIndicator={false}
          >
            <View style={styles.heroPanel}>
              <View style={styles.heroIcon}>
                <Ionicons name="location" size={22} color="#FFFFFF" />
              </View>
              <View style={styles.heroCopy}>
                <Text style={styles.heroTitle}>Datos de entrega</Text>
                <Text style={styles.heroText}>Guarda una direccion clara para entregar sin llamadas extra.</Text>
              </View>
            </View>

            <View style={styles.section}>
              <Text style={styles.sectionLabel}>Tipo de direccion</Text>
              <View style={styles.presetsContainer}>
                {ALIAS_PRESETS.map((preset) => {
                  const isSelected = isCustomAlias ? preset.id === 'Otro' : alias === preset.id;
                  return (
                    <TouchableOpacity
                      key={preset.id}
                      activeOpacity={0.78}
                      style={[styles.presetButton, isSelected && styles.presetButtonActive]}
                      onPress={() => handlePresetPress(preset.id)}
                    >
                      <Ionicons
                        name={(isSelected ? preset.iconFilled : preset.iconOutline) as any}
                        size={20}
                        color={isSelected ? '#FFFFFF' : '#475569'}
                      />
                      <Text style={[styles.presetButtonText, isSelected && styles.presetButtonTextActive]}>
                        {preset.label}
                      </Text>
                    </TouchableOpacity>
                  );
                })}
              </View>
            </View>

            {isCustomAlias ? (
              <FormField
                label="Nombre personalizado"
                value={alias}
                onChangeText={(text) => {
                  setAlias(text);
                  if (text.trim()) setAliasError(null);
                }}
                placeholder="Ej: Oficina, departamento"
                error={aliasError}
                icon="bookmark-outline"
                autoCapitalize="words"
              />
            ) : null}

            <View style={styles.divider} />

            <FormField
              label="Calle o Avenida"
              value={calle}
              onChangeText={(text) => {
                setCalle(text);
                setLat(null);
                setLng(null);
                if (text.trim()) setCalleError(null);
              }}
              placeholder="Ej: Avenida Universidad"
              error={calleError}
              icon="map-outline"
              autoCapitalize="words"
              onFocus={() => setStreetFocused(true)}
              onBlur={() => setTimeout(() => setStreetFocused(false), 160)}
              inputWrapperStyle={streetFocused && !calleError ? styles.inputWrapperActive : undefined}
            />

            {showSuggestions ? (
              <View style={styles.suggestionsCard}>
                <View style={styles.suggestionsHeader}>
                  <Text style={styles.suggestionsTitle}>Sugerencias</Text>
                  {suggestionsLoading ? <ActivityIndicator size="small" color={Colors.primary} /> : null}
                </View>
                {addressSuggestions.length > 0 ? (
                  addressSuggestions.map((suggestion) => (
                    <TouchableOpacity
                      key={suggestion.id}
                      style={styles.suggestionRow}
                      activeOpacity={0.82}
                      onPress={() => handleSuggestionPress(suggestion)}
                    >
                      <View style={styles.suggestionIcon}>
                        <Ionicons name={suggestion.icon} size={17} color={Colors.primary} />
                      </View>
                      <View style={styles.suggestionCopy}>
                        <Text style={styles.suggestionLabel} numberOfLines={1}>{suggestion.label}</Text>
                        {suggestion.detail ? (
                          <Text style={styles.suggestionDetail} numberOfLines={1}>{suggestion.detail}</Text>
                        ) : null}
                      </View>
                    </TouchableOpacity>
                  ))
                ) : !suggestionsLoading ? (
                  <Text style={styles.suggestionsEmpty}>Sigue escribiendo calle, avenida o colonia.</Text>
                ) : null}
              </View>
            ) : null}

            <View style={styles.formRow}>
              <View style={styles.formColumn}>
                <FormField
                  label="Numero"
                  value={numero}
                  onChangeText={setNumero}
                  placeholder="Ej: 405-B"
                  icon="business-outline"
                  autoCapitalize="characters"
                />
              </View>
              <View style={styles.formColumn}>
                <FormField
                  label="Codigo Postal"
                  value={cp}
                  onChangeText={setCp}
                  placeholder="Ej: 76000"
                  keyboardType="numeric"
                  icon="mail-open-outline"
                />
              </View>
            </View>

            <FormField
              label="Colonia o Asentamiento"
              value={colonia}
              onChangeText={setColonia}
              placeholder="Ej: Alamos 3ra Seccion"
              icon="navigate-outline"
              autoCapitalize="words"
            />

            <FormField
              label="Ciudad / Municipio"
              value={ciudad}
              onChangeText={(text) => {
                setCiudad(text);
                if (text.trim()) setCiudadError(null);
              }}
              placeholder="Ej: Queretaro"
              error={ciudadError}
              icon="location-outline"
              autoCapitalize="words"
            />

            <FormField
              label="Referencias de entrega"
              value={instrucciones}
              onChangeText={setInstrucciones}
              placeholder="Ej: Porton de madera, entre Hidalgo y Juarez"
              icon="chatbubble-ellipses-outline"
              autoCapitalize="sentences"
              inputStyle={styles.referenceInput}
              inputWrapperStyle={styles.referenceWrapper}
            />
          </ScrollView>

          <View style={[styles.footer, { paddingBottom: Spacing.base + Math.max(insets.bottom, Platform.OS === 'android' ? 8 : 0) }]}>
            <Button
              label={address ? 'Guardar cambios' : 'Guardar direccion'}
              onPress={handleSave}
              loading={saveMutation.isPending}
              fullWidth
              size="lg"
              disabled={saveMutation.isPending}
              style={styles.saveButton}
              textStyle={styles.saveButtonText}
            />
          </View>
        </KeyboardAvoidingView>
      </SafeAreaView>
    </Modal>
  );
}

const styles = StyleSheet.create({
  safe: {
    flex: 1,
    backgroundColor: '#F8FAFC',
  },
  flex: {
    flex: 1,
  },
  header: {
    minHeight: 64,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: Spacing.base,
    borderBottomWidth: 1,
    borderBottomColor: '#E5E7EB',
    backgroundColor: '#FFFFFF',
  },
  closeButton: {
    width: 42,
    height: 42,
    borderRadius: 21,
    alignItems: 'center',
    justifyContent: 'center',
  },
  headerTitle: {
    fontSize: 19,
    fontWeight: '900',
    color: '#111827',
  },
  content: {
    padding: Spacing.base,
    gap: 14,
    paddingBottom: Spacing.xl * 2,
  },
  heroPanel: {
    minHeight: 78,
    padding: 14,
    borderRadius: 18,
    backgroundColor: '#FFF7F8',
    borderWidth: 1,
    borderColor: '#FFE1E6',
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  heroIcon: {
    width: 46,
    height: 46,
    borderRadius: 16,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: Colors.primary,
  },
  heroCopy: {
    flex: 1,
    minWidth: 0,
  },
  heroTitle: {
    fontSize: 15,
    fontWeight: '900',
    color: '#111827',
  },
  heroText: {
    marginTop: 3,
    fontSize: 12,
    lineHeight: 17,
    color: '#64748B',
  },
  section: {
    gap: 10,
  },
  sectionLabel: {
    fontSize: 14,
    fontWeight: '800',
    color: '#334155',
    marginLeft: 2,
  },
  presetsContainer: {
    flexDirection: 'row',
    gap: 9,
  },
  presetButton: {
    flex: 1,
    minHeight: 58,
    flexDirection: 'row',
    gap: 7,
    borderRadius: 16,
    borderWidth: 1.5,
    borderColor: '#E5E7EB',
    backgroundColor: '#FFFFFF',
    justifyContent: 'center',
    alignItems: 'center',
  },
  presetButtonActive: {
    borderColor: Colors.primary,
    backgroundColor: Colors.primary,
  },
  presetButtonText: {
    fontSize: 14,
    fontWeight: '800',
    color: '#475569',
  },
  presetButtonTextActive: {
    color: '#FFFFFF',
  },
  divider: {
    height: 1,
    backgroundColor: '#E5E7EB',
    marginVertical: 2,
  },
  inputWrapperActive: {
    borderColor: Colors.primary,
    backgroundColor: '#FFFFFF',
  },
  suggestionsCard: {
    marginTop: -8,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    backgroundColor: '#FFFFFF',
    overflow: 'hidden',
  },
  suggestionsHeader: {
    minHeight: 34,
    paddingHorizontal: 13,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    backgroundColor: '#F8FAFC',
  },
  suggestionsTitle: {
    fontSize: 12,
    fontWeight: '900',
    color: '#64748B',
    textTransform: 'uppercase',
  },
  suggestionRow: {
    minHeight: 58,
    paddingHorizontal: 12,
    paddingVertical: 10,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    borderTopWidth: 1,
    borderTopColor: '#F1F5F9',
  },
  suggestionIcon: {
    width: 34,
    height: 34,
    borderRadius: 12,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#FFF7F8',
  },
  suggestionCopy: {
    flex: 1,
    minWidth: 0,
  },
  suggestionLabel: {
    fontSize: 14,
    fontWeight: '800',
    color: '#111827',
  },
  suggestionDetail: {
    marginTop: 2,
    fontSize: 12,
    color: '#64748B',
  },
  suggestionsEmpty: {
    paddingHorizontal: 13,
    paddingVertical: 12,
    borderTopWidth: 1,
    borderTopColor: '#F1F5F9',
    fontSize: 13,
    color: '#64748B',
  },
  formRow: {
    flexDirection: 'row',
    gap: 12,
  },
  formColumn: {
    flex: 1,
    minWidth: 0,
  },
  referenceWrapper: {
    minHeight: 58,
    alignItems: 'flex-start',
    paddingTop: Platform.OS === 'ios' ? 12 : 9,
  },
  referenceInput: {
    minHeight: 44,
    textAlignVertical: 'top',
  },
  footer: {
    padding: Spacing.base,
    borderTopWidth: 1,
    borderTopColor: '#E5E7EB',
    backgroundColor: '#FFFFFF',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: -4 },
    shadowOpacity: 0.06,
    shadowRadius: 9,
    elevation: 9,
  },
  saveButton: {
    borderRadius: 18,
  },
  saveButtonText: {
    fontWeight: '900',
  },
});
