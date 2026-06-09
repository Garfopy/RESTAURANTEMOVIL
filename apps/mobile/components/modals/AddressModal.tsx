import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  Modal,
  SafeAreaView,
  ScrollView,
  TouchableOpacity,
  KeyboardAvoidingView,
  Platform,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
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

// Configuración enriquecida para los presets visuales
const ALIAS_PRESETS = [
  { id: 'Casa', label: 'Casa', iconOutline: 'home-outline', iconFilled: 'home' },
  { id: 'Trabajo', label: 'Trabajo', iconOutline: 'briefcase-outline', iconFilled: 'briefcase' },
  { id: 'Otro', label: 'Otro', iconOutline: 'ellipsis-horizontal-outline', iconFilled: 'ellipsis-horizontal' },
];

export function AddressModal({
  visible,
  onDismiss,
  address,
  onSuccess,
}: AddressModalProps) {
  const toast = useToast();
  const qc = useQueryClient();

  // Form state
  const [alias, setAlias] = useState('');
  const [isCustomAlias, setIsCustomAlias] = useState(false);
  const [calle, setCalle] = useState('');
  const [numero, setNumero] = useState('');
  const [colonia, setColonia] = useState('');
  const [ciudad, setCiudad] = useState('');
  const [cp, setCp] = useState('');
  const [instrucciones, setInstrucciones] = useState('');

  // Validation state
  const [aliasError, setAliasError] = useState<string | null>(null);
  const [calleError, setCalleError] = useState<string | null>(null);
  const [ciudadError, setCiudadError] = useState<string | null>(null);

  // Pre-fill / Reset logic
  useEffect(() => {
    if (address) {
      const isPreset = ALIAS_PRESETS.some(p => p.id === address.alias);
      setAlias(address.alias || '');
      setIsCustomAlias(!isPreset && !!address.alias);
      setCalle(address.calle || '');
      setNumero(address.numero || '');
      setColonia(address.colonia || '');
      setCiudad(address.ciudad || '');
      setCp(address.cp || '');
      setInstrucciones(address.instrucciones || '');
    } else {
      if (!visible) return;
      resetForm();
    }
  }, [visible, address]);

  const resetForm = () => {
    setAlias('');
    setIsCustomAlias(false);
    setCalle('');
    setNumero('');
    setColonia('');
    setCiudad('');
    setCp('');
    setInstrucciones('');
    setAliasError(null);
    setCalleError(null);
    setCiudadError(null);
  };

  const saveMutation = useMutation({
    mutationFn: async (data: any) => {
      if (address) {
        const { data: res } = await apiClient.put(`/profile/addresses/${address.id}`, data);
        return res.data;
      } else {
        const { data: res } = await apiClient.post('/profile/addresses', data);
        return res.data;
      }
    },
    onSuccess: (savedAddress) => {
      qc.invalidateQueries({ queryKey: ['addresses'] });
      toast.success(address ? 'Dirección actualizada' : 'Dirección guardada');
      onSuccess?.(savedAddress);
      resetForm();
      onDismiss();
    },
    onError: (err) => {
      const friendlyError = mapErrorToFriendly(err);
      toast.error(friendlyError.message);
    },
  });

  const validateForm = () => {
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
  };

  const handleSave = () => {
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
      instrucciones: instrucciones.trim() || null,
      es_principal: address?.es_principal ?? false,
    });
  };

  const handlePresetPress = (presetId: string) => {
    setAliasError(null);
    if (presetId === 'Otro') {
      setAlias('');
      setIsCustomAlias(true);
    } else {
      setAlias(presetId);
      setIsCustomAlias(false);
    }
  };

  return (
    <Modal
      visible={visible}
      animationType="slide"
      presentationStyle="pageSheet" // Ofrece una estética de tarjeta nativa hermosa en iOS
      onRequestClose={onDismiss}
    >
      <SafeAreaView style={styles.safe}>
        <KeyboardAvoidingView
          behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
          style={{ flex: 1 }}
        >
          {/* Header */}
          <View style={styles.header}>
            <TouchableOpacity onPress={onDismiss} hitSlop={{ top: 15, bottom: 15, left: 15, right: 15 }}>
              <Ionicons name="close-outline" size={26} color={Colors.text} />
            </TouchableOpacity>
            <Text style={styles.headerTitle}>
              {address ? 'Editar dirección' : 'Nueva dirección'}
            </Text>
            <View style={{ width: 26 }} />
          </View>

          {/* Form Content */}
          <ScrollView
            contentContainerStyle={styles.content}
            keyboardShouldPersistTaps="handled"
            showsVerticalScrollIndicator={false}
          >
            {/* Tipo de Dirección (Chips/Presets) */}
            <View style={styles.section}>
              <Text style={styles.sectionLabel}>¿Qué tipo de dirección es?</Text>
              <View style={styles.presetsContainer}>
                {ALIAS_PRESETS.map((preset) => {
                  const isSelected = isCustomAlias ? preset.id === 'Otro' : alias === preset.id;
                  return (
                    <TouchableOpacity
                      key={preset.id}
                      activeOpacity={0.7}
                      style={[
                        styles.presetButton,
                        isSelected && styles.presetButtonActive,
                      ]}
                      onPress={() => handlePresetPress(preset.id)}
                    >
                      <Ionicons
                        name={(isSelected ? preset.iconFilled : preset.iconOutline) as any}
                        size={20}
                        color={isSelected ? '#FFFFFF' : Colors.textSecondary}
                      />
                      <Text style={[styles.presetButtonText, isSelected && styles.presetButtonTextActive]}>
                        {preset.label}
                      </Text>
                    </TouchableOpacity>
                  );
                })}
              </View>
            </View>

            {/* Custom Alias Input Dinámico */}
            {isCustomAlias && (
              <FormField
                label="Nombre personalizado"
                value={alias}
                onChangeText={(text) => {
                  setAlias(text);
                  if (text.trim()) setAliasError(null);
                }}
                placeholder="Ej: Oficina, Departamento de novio, etc."
                error={aliasError}
                icon="bookmark-outline"
              />
            )}

            <View style={styles.divider} />

            {/* Dirección de Calle Principal */}
            <FormField
              label="Calle o Avenida"
              value={calle}
              onChangeText={(text) => {
                setCalle(text);
                if (text.trim()) setCalleError(null);
              }}
              placeholder="Ej: Avenida Universidad"
              error={calleError}
              icon="map-outline" // ¡Corregido aquí para evitar el warning!
            />

            {/* Grid de Dos Columnas: Número y Código Postal */}
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
                  label="Código Postal"
                  value={cp}
                  onChangeText={setCp}
                  placeholder="Ej: 76000"
                  keyboardType="numeric"
                  icon="mail-open-outline"
                />
              </View>
            </View>

            {/* Colonia */}
            <FormField
              label="Colonia o Asentamiento"
              value={colonia}
              onChangeText={setColonia}
              placeholder="Ej: Alamos 3ra Sección"
              icon="navigate-outline"
            />

            {/* Ciudad */}
            <FormField
              label="Ciudad / Municipio"
              value={ciudad}
              onChangeText={(text) => {
                setCiudad(text);
                if (text.trim()) setCiudadError(null);
              }}
              placeholder="Ej: Querétaro"
              error={ciudadError}
              icon="location-outline"
            />

            {/* Instrucciones */}
            <FormField
              label="Referencias de entrega"
              value={instrucciones}
              onChangeText={setInstrucciones}
              placeholder="Ej: Portón de madera, entre calles Hidalgo y Juárez"
              icon="chatbubble-ellipses-outline"
            />
          </ScrollView>

          {/* Footer Fijo y Flotante */}
          <View style={styles.footer}>
            <Button
              label={address ? 'Guardar cambios' : 'Guardar dirección'}
              onPress={handleSave}
              loading={saveMutation.isPending}
              fullWidth
              size="lg"
              disabled={saveMutation.isPending}
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
    backgroundColor: Colors.background,
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: Spacing.base,
    paddingVertical: Spacing.md,
    borderBottomWidth: 1,
    borderBottomColor: Colors.border,
    backgroundColor: Colors.surface,
  },
  headerTitle: {
    fontSize: 18,
    fontWeight: '700',
    color: Colors.text,
    letterSpacing: -0.3,
  },
  content: {
    padding: Spacing.base,
    gap: Spacing.md,
    paddingBottom: Spacing.xl * 2, // Espacio extra para que el scroll libre el botón flotante
  },
  section: {
    marginBottom: Spacing.xs,
  },
  sectionLabel: {
    fontSize: 14,
    fontWeight: '600',
    color: Colors.textSecondary,
    marginBottom: Spacing.sm,
  },
  presetsContainer: {
    flexDirection: 'row',
    gap: Spacing.sm,
  },
  presetButton: {
    flex: 1,
    flexDirection: 'row',
    gap: 6,
    paddingVertical: Spacing.md,
    borderRadius: 12,
    borderWidth: 1.5,
    borderColor: Colors.border,
    backgroundColor: Colors.surface,
    justifyContent: 'center',
    alignItems: 'center',
  },
  presetButtonActive: {
    borderColor: Colors.accent,
    backgroundColor: Colors.accent,
  },
  presetButtonText: {
    fontSize: 14,
    fontWeight: '600',
    color: Colors.textSecondary,
  },
  presetButtonTextActive: {
    color: '#FFFFFF',
  },
  divider: {
    height: 1,
    backgroundColor: Colors.border,
    marginVertical: Spacing.xs,
  },
  formRow: {
    flexDirection: 'row',
    gap: Spacing.md,
  },
  formColumn: {
    flex: 1,
  },
  footer: {
    padding: Spacing.base,
    borderTopWidth: 1,
    borderTopColor: Colors.border,
    backgroundColor: Colors.surface,
    // Sombra sutil para despegar el botón flotante del fondo de scroll
    shadowColor: '#000',
    shadowOffset: { width: 0, height: -3 },
    shadowOpacity: 0.04,
    shadowRadius: 6,
    elevation: 8,
  },
});