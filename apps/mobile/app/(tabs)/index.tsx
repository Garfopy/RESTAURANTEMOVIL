import React, { useState, useRef, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  FlatList,
  Pressable,
  TouchableOpacity,
  StatusBar,
  Animated,
  Modal,
  KeyboardAvoidingView,
  Platform,
  ActivityIndicator,
  RefreshControl,
  useWindowDimensions,
  Alert,
  TextInput,
} from 'react-native';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import { Ionicons } from '@expo/vector-icons';
import DateTimePicker, {
  DateTimePickerAndroid,
  type DateTimePickerEvent,
} from '@react-native-community/datetimepicker';
import * as Location from 'expo-location';
import { useQueryClient } from '@tanstack/react-query';
import { useUserStore } from '../../store/user.store';
import { useBranchConfigStore, useBranchStore } from '../../store/branch.store';
import { useCartStore } from '../../store/cart.store';
import { useTableSessionStore } from '../../store/table-session.store';
import { useBranches } from '../../hooks/useBranches';
import { useCategories, useDishes } from '../../hooks/useMenu';
import { getNearestBranches } from '../../services/branches.service';
import { ensureCameraPermission, ensureLocationPermission } from '../../services/app-permissions.service';
import { createReservation, getReservationAvailability, type ReservationTable } from '../../services/reservations.service';
import { getApiError } from '../../services/api';
import { Colors } from '../../theme';
import { BannerCarousel } from '../../components/shared/BannerCarousel';
import { CategoryCard } from '../../components/cards/CategoryCard';
import { ProductCard } from '../../components/cards/ProductCard';
import { SearchBar } from '../../components/ui/SearchBar';
import { StoreFAB } from '../../components/shared/StoreFAB';
import { Skeleton } from '../../components/ui/Skeleton';
import { OrderTypeSelector } from '../../components/shared/OrderTypeSelector';
import { SafeMapView } from '../../components/shared/SafeMapView';
import { useToast } from '../../context/ToastContext';
import { DeliveryAddressModal } from '../../components/modals/DeliveryAddressModal';
import type { Platillo, Categoria, TipoPedido, Sucursal } from '@amare/types';
import type { DeliveryAddressSelection } from '../../store/cart.store';

const HOME_BANNERS = [
  {
    id: '1',
    titulo: '¡2x1 en Pizzas!',
    subtitulo: 'Aprovecha todos los martes y jueves en sucursal.',
    imagen: 'https://images.unsplash.com/photo-1513104890138-7c749659a591?auto=format&fit=crop&w=800',
    deepLink: '/promotions',
  },
  {
    id: '2',
    titulo: 'Envío Gratis',
    subtitulo: 'En tu primer pedido mayor a $350 MXN.',
    imagen: 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRbwIYrgWuk2sbrX9QAJWIixayxHtlH2f9s8Q&s',
    deepLink: '/(tabs)/index',
  },
  {
    id: '3',
    titulo: 'Nuevos Postres',
    subtitulo: 'Descubre nuestra selección de repostería artesanal.',
    imagen: 'https://images.unsplash.com/photo-1551024601-bec78aea704b?auto=format&fit=crop&w=800',
    deepLink: '/(tabs)/index',
  },
];

const AUTO_SELECT_DISTANCE_METERS = 100;
const CONFIRM_SELECT_DISTANCE_METERS = 333;
const MIN_DISTANCE_GAP_METERS = 200;
const RESERVATION_MINUTE_STEP = 15;

type ReservationPickerMode = 'date' | 'time';

function formatReservationDateValue(date: Date): string {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

function formatReservationTimeValue(date: Date): string {
  return `${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`;
}

function parseReservationDateTime(dateValue: string, timeValue: string): Date | null {
  const dateMatch = /^(\d{4})-(\d{2})-(\d{2})$/.exec(dateValue.trim());
  const timeMatch = /^(\d{2}):(\d{2})$/.exec(timeValue.trim());
  if (!dateMatch || !timeMatch) return null;

  const selected = new Date(
    Number(dateMatch[1]),
    Number(dateMatch[2]) - 1,
    Number(dateMatch[3]),
    Number(timeMatch[1]),
    Number(timeMatch[2]),
    0,
    0
  );
  if (
    selected.getFullYear() !== Number(dateMatch[1]) ||
    selected.getMonth() !== Number(dateMatch[2]) - 1 ||
    selected.getDate() !== Number(dateMatch[3]) ||
    selected.getHours() !== Number(timeMatch[1]) ||
    selected.getMinutes() !== Number(timeMatch[2])
  ) {
    return null;
  }

  return selected;
}

function getNextReservationSlot(): Date {
  const slot = new Date(Date.now() + 30 * 60 * 1000);
  slot.setSeconds(0, 0);
  slot.setMinutes(Math.ceil(slot.getMinutes() / RESERVATION_MINUTE_STEP) * RESERVATION_MINUTE_STEP);
  return slot;
}

function formatReservationDateLabel(value: string): string {
  const selected = parseReservationDateTime(value, '12:00');
  if (!selected) return 'Seleccionar fecha';
  return new Intl.DateTimeFormat('es-MX', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  }).format(selected);
}

function formatReservationTimeLabel(dateValue: string, timeValue: string): string {
  const selected = parseReservationDateTime(dateValue, timeValue);
  if (!selected) return 'Seleccionar hora';
  return new Intl.DateTimeFormat('es-MX', {
    hour: 'numeric',
    minute: '2-digit',
  }).format(selected);
}

type NearbyBranchState =
  | { kind: 'inside'; branch: Sucursal; distanceMeters: number }
  | { kind: 'near'; branch: Sucursal; distanceMeters: number }
  | { kind: 'far'; branch: Sucursal | null; distanceMeters: number | null }
  | { kind: 'unknown'; branch: Sucursal | null; distanceMeters: number | null };

export default function HomeScreen() {
  const router = useRouter();
  const { tableScanDeferred } = useLocalSearchParams<{ tableScanDeferred?: string }>();
  const queryClient = useQueryClient();
  const { width } = useWindowDimensions();
  const user = useUserStore((s) => s.user);
  const toast = useToast();
  const { seleccionada: branch, sucursales, seleccionar } = useBranchStore();
  const { tipoPedido, setTipoPedido, setDeliveryAddress, itemCount, restauranteId: cartRestaurantId, clear } = useCartStore();
  const tableSession = useTableSessionStore((s) => s.session);
  const deferredBranch = useTableSessionStore((s) => s.deferredBranch);
  const deferScan = useTableSessionStore((s) => s.deferScan);
  const clearTableSession = useTableSessionStore((s) => s.clearSession);
  
  const [showTypeModal, setShowTypeModal] = useState(false);
  const [showDeliveryAddressModal, setShowDeliveryAddressModal] = useState(false);
  const [availableTypes, setAvailableTypes] = useState<TipoPedido[]>(['delivery', 'pickup']);
  const [detectingLocation, setDetectingLocation] = useState(false);
  const [detectedCoords, setDetectedCoords] = useState<{ lat: number; lng: number } | null>(null);
  const [detectedBranchMessage, setDetectedBranchMessage] = useState<string | null>(null);
  const [selectingPickupBranch, setSelectingPickupBranch] = useState(false);
  const [pickupListExpanded, setPickupListExpanded] = useState(true);
  const [search, setSearch] = useState('');
  const [refreshingHome, setRefreshingHome] = useState(false);
  const isPermissionsResolved = true;
  const [reservationVisible, setReservationVisible] = useState(false);
  const [reservationName, setReservationName] = useState('');
  const [reservationPhone, setReservationPhone] = useState('');
  const [reservationEmail, setReservationEmail] = useState('');
  const [reservationDate, setReservationDate] = useState('');
  const [reservationTime, setReservationTime] = useState('');
  const [reservationPickerMode, setReservationPickerMode] = useState<ReservationPickerMode | null>(null);
  const [reservationPickerDraft, setReservationPickerDraft] = useState(() => getNextReservationSlot());
  const [reservationPeople, setReservationPeople] = useState(2);
  const [reservationNotes, setReservationNotes] = useState('');
  const [reservationTables, setReservationTables] = useState<ReservationTable[]>([]);
  const [selectedReservationTableId, setSelectedReservationTableId] = useState<number | null>(null);
  const [reservationLoading, setReservationLoading] = useState(false);
  const [reservationSubmitting, setReservationSubmitting] = useState(false);
  const initialFlowStartedRef = useRef(false);
  const homeIntro = useRef(new Animated.Value(0)).current;
  const heroGlow = useRef(new Animated.Value(0)).current;

  useEffect(() => {
    Animated.spring(homeIntro, { toValue: 1, damping: 19, stiffness: 95, useNativeDriver: true }).start();
    Animated.loop(
      Animated.sequence([
        Animated.timing(heroGlow, { toValue: 1, duration: 2400, useNativeDriver: true }),
        Animated.timing(heroGlow, { toValue: 0, duration: 2400, useNativeDriver: true }),
      ])
    ).start();
  }, [heroGlow, homeIntro]);

  // 🎞️ Animación para los indicadores (dots)
  const scrollX = useRef(new Animated.Value(0)).current;
  const featuredGap = 12;
  const featuredInset = width < 380 ? 16 : 20;
  const availableFeaturedWidth = Math.max(width - featuredInset * 2, 280);
  const featuredCardWidth = Math.min(Math.max(availableFeaturedWidth * 0.78, 240), 320);
  const featuredSnapInterval = featuredCardWidth + featuredGap;

  // 🔥 CRITICAL: Invocar este hook para que sincronice las sucursales desde el API
  // al Zustand store. Sin esto, autoSeleccionarSiUnica() nunca se ejecuta y
  // restauranteId queda undefined → las queries del menú nunca se habilitan.
  useBranches();

  const menuBranch =
    branch ??
    sucursales.find((item) => item.tipos_entrega?.includes('eat_in')) ??
    sucursales[0] ??
    null;
  const restauranteId = menuBranch?.id ?? user?.current_restaurante_id ?? undefined;
  const { data: categories, isLoading: loadingCats, refetch: refetchCategories } = useCategories(restauranteId);
  const { data: allDishes, isLoading: loadingAllDishes, refetch: refetchAllDishes } = useDishes(restauranteId);
  const refreshBranchConfig = useBranchConfigStore((state) => state.refresh);
  const visibleCategories =
    categories && categories.length > 0
      ? categories
      : Array.from(
          new Map(
            (allDishes ?? [])
              .filter((item) => item.categoria_id && item.categoria_nombre)
              .map((item) => [
                Number(item.categoria_id),
                {
                  id: Number(item.categoria_id),
                  nombre: item.categoria_nombre ?? 'Menu',
                  descripcion: null,
                  imagen: item.imagen ?? null,
                  orden: 0,
                  activo: true,
                  total_platillos: 0,
                } as Categoria,
              ])
          ).values()
        );
  const visibleFeatured = (allDishes ?? []).slice(0, 8);
  const loadingMenuItems = loadingAllDishes;

  useEffect(() => {
    if (tipoPedido === 'eat_in' && !tableSession && !deferredBranch) {
      setTipoPedido(null);
    }
  }, [deferredBranch, setTipoPedido, tableSession, tipoPedido]);

  async function refreshHome() {
    setRefreshingHome(true);
    try {
      await Promise.all([
        restauranteId ? refreshBranchConfig(restauranteId, { force: true }) : Promise.resolve(),
        refetchCategories(),
        refetchAllDishes(),
      ]);
    } catch {
      toast.warning('No pudimos actualizar el menú. Conservamos la última versión disponible.');
    } finally {
      setRefreshingHome(false);
    }
  }

  function getReservationBranch(): Sucursal | null {
    return branch ?? menuBranch;
  }

  function canShowReservations(): boolean {
    const targetBranch = getReservationBranch();
    return Boolean(targetBranch?.reservas_habilitadas && !(tipoPedido === 'eat_in' && tableSession));
  }

  function normalizeReservationPhone(value: string): string {
    const digits = value.replace(/\D+/g, '');
    if (digits.length === 12 && digits.startsWith('52')) {
      return digits.slice(2);
    }
    if (digits.length === 13 && digits.startsWith('521')) {
      return digits.slice(3);
    }
    return digits.length > 10 ? digits.slice(-10) : digits;
  }

  function openReservationModal() {
    const userAny = user as any;
    const nextSlot = getNextReservationSlot();
    const currentSelection = parseReservationDateTime(reservationDate, reservationTime);
    const initialSelection = currentSelection && currentSelection.getTime() > Date.now()
      ? currentSelection
      : nextSlot;
    setReservationName((user?.nombre ?? '').trim());
    setReservationPhone(normalizeReservationPhone(String(userAny?.telefono ?? userAny?.phone ?? '').trim()));
    setReservationEmail(String(userAny?.email ?? userAny?.correo ?? '').trim());
    setReservationDate(formatReservationDateValue(initialSelection));
    setReservationTime(formatReservationTimeValue(initialSelection));
    setReservationPeople((current) => current || 2);
    setReservationNotes('');
    setReservationTables([]);
    setSelectedReservationTableId(null);
    setReservationVisible(true);
  }

  function clearReservationAvailability() {
    setReservationTables([]);
    setSelectedReservationTableId(null);
  }

  function applyReservationPickerValue(mode: ReservationPickerMode, selected: Date) {
    if (mode === 'date') {
      setReservationDate(formatReservationDateValue(selected));
    } else {
      setReservationTime(formatReservationTimeValue(selected));
    }
    clearReservationAvailability();
  }

  function openReservationPicker(mode: ReservationPickerMode) {
    const selected = parseReservationDateTime(reservationDate, reservationTime) ?? getNextReservationSlot();

    if (Platform.OS === 'android') {
      const today = new Date();
      today.setHours(0, 0, 0, 0);
      const maximumDate = new Date(today);
      maximumDate.setFullYear(maximumDate.getFullYear() + 1);
      DateTimePickerAndroid.open({
        value: selected,
        mode,
        minimumDate: mode === 'date' ? today : undefined,
        maximumDate: mode === 'date' ? maximumDate : undefined,
        onChange: (event: DateTimePickerEvent, value?: Date) => {
          if (event.type === 'set' && value) applyReservationPickerValue(mode, value);
        },
      });
      return;
    }

    setReservationPickerDraft(selected);
    setReservationPickerMode(mode);
  }

  function confirmReservationPicker() {
    if (!reservationPickerMode) return;
    applyReservationPickerValue(reservationPickerMode, reservationPickerDraft);
    setReservationPickerMode(null);
  }

  function changeReservationPeople(amount: number) {
    setReservationPeople((value) => Math.min(20, Math.max(1, value + amount)));
    clearReservationAvailability();
  }

  function validateReservationForm(): string | null {
    if (!getReservationBranch()?.id) return 'Selecciona una sucursal para reservar.';
    if (!reservationName.trim()) return 'Ingresa tu nombre.';
    if (!/^\d{10}$/.test(normalizeReservationPhone(reservationPhone))) return 'Ingresa un telefono de 10 digitos.';
    if (!/^\d{4}-\d{2}-\d{2}$/.test(reservationDate.trim())) return 'Usa fecha en formato aaaa-mm-dd.';
    if (!/^\d{2}:\d{2}$/.test(reservationTime.trim())) return 'Usa hora en formato hh:mm.';
    const selectedDateTime = parseReservationDateTime(reservationDate, reservationTime);
    if (!selectedDateTime || selectedDateTime.getTime() <= Date.now()) return 'Elige una fecha y hora futuras.';
    if (reservationPeople < 1) return 'Indica el numero de personas.';
    return null;
  }

  async function refreshReservationTables() {
    const validation = validateReservationForm();
    if (validation) {
      Alert.alert('Revisa tu reserva', validation);
      return;
    }

    const targetBranch = getReservationBranch();
    if (!targetBranch) return;

    setReservationLoading(true);
    try {
      const availability = await getReservationAvailability({
        restaurantId: targetBranch.id,
        fecha: reservationDate.trim(),
        hora: reservationTime.trim(),
        personas: reservationPeople,
      });
      setReservationTables(availability.tables);
      setSelectedReservationTableId(availability.tables[0]?.id ?? null);
      if (availability.tables.length === 0) {
        Alert.alert('Sin mesas disponibles', 'No encontramos mesas libres para ese horario. Intenta otra hora.');
      }
    } catch (error) {
      Alert.alert('No pudimos consultar mesas', 'Intenta de nuevo en unos momentos.');
    } finally {
      setReservationLoading(false);
    }
  }

  async function submitReservation() {
    const validation = validateReservationForm();
    if (validation) {
      Alert.alert('Revisa tu reserva', validation);
      return;
    }
    const targetBranch = getReservationBranch();
    if (!targetBranch || !selectedReservationTableId) {
      Alert.alert('Mesa requerida', 'Consulta disponibilidad y selecciona una mesa.');
      return;
    }

    setReservationSubmitting(true);
    try {
      const availability = await getReservationAvailability({
        restaurantId: targetBranch.id,
        fecha: reservationDate.trim(),
        hora: reservationTime.trim(),
        personas: reservationPeople,
      });
      const stillAvailable = availability.tables.some((item) => item.id === selectedReservationTableId);
      if (!stillAvailable) {
        setReservationTables(availability.tables);
        setSelectedReservationTableId(availability.tables[0]?.id ?? null);
        Alert.alert('Mesa no disponible', 'La mesa seleccionada ya no esta libre en ese horario. Elige otra mesa.');
        return;
      }

      await createReservation({
        restaurantId: targetBranch.id,
        mesaId: selectedReservationTableId,
        nombre: reservationName.trim(),
        telefono: normalizeReservationPhone(reservationPhone),
        email: reservationEmail.trim(),
        fecha: reservationDate.trim(),
        hora: reservationTime.trim(),
        personas: reservationPeople,
        notas: reservationNotes.trim(),
      });
      setReservationVisible(false);
      Alert.alert('Reserva confirmada', 'Tu mesa quedo reservada. Te esperamos en Amare.');
    } catch (error) {
      Alert.alert('No pudimos reservar', getApiError(error));
    } finally {
      setReservationSubmitting(false);
    }
  }

  async function getDetectedCoords(options?: { silentOnDenied?: boolean }): Promise<{ lat: number; lng: number } | null> {
    if (detectedCoords) {
      return detectedCoords;
    }

    setDetectingLocation(true);

    try {
      const permission = await ensureLocationPermission();

      if (!permission.granted) {
        if (!options?.silentOnDenied) {
          toast.info('Activa tu ubicación para detectar tu sucursal automáticamente.');
        }
        return null;
      }

      const location = await Location.getCurrentPositionAsync({
        accuracy: Location.Accuracy.High,
      });

      const coords = {
        lat: location.coords.latitude,
        lng: location.coords.longitude,
      };

      setDetectedCoords(coords);
      return coords;
    } catch (error) {
      console.error('Error detectando ubicación:', error);

      if (!options?.silentOnDenied) {
        toast.warning('No pudimos obtener tu ubicación. Puedes elegir la sucursal manualmente.');
      }

      return null;
    } finally {
      setDetectingLocation(false);
    }
  }

  async function evaluateNearbyBranch(): Promise<NearbyBranchState> {
    const coords = await getDetectedCoords({ silentOnDenied: true });
    if (!coords) {
      return { kind: 'unknown', branch: null, distanceMeters: null };
    }

    try {
      const nearest = await getNearestBranches(coords.lat, coords.lng);
      const candidate = nearest[0];
      const secondCandidate = nearest[1];

      if (!candidate || typeof candidate.distancia_km !== 'number') {
        return { kind: 'unknown', branch: null, distanceMeters: null };
      }

      const candidateDistanceMeters = candidate.distancia_km * 1000;
      const secondDistanceMeters =
        typeof secondCandidate?.distancia_km === 'number' ? secondCandidate.distancia_km * 1000 : null;
      const hasCloseRunnerUp =
        secondDistanceMeters !== null && secondDistanceMeters - candidateDistanceMeters <= MIN_DISTANCE_GAP_METERS;

      if (candidateDistanceMeters <= AUTO_SELECT_DISTANCE_METERS && !hasCloseRunnerUp) {
        return { kind: 'inside', branch: candidate, distanceMeters: candidateDistanceMeters };
      }

      if (candidateDistanceMeters <= CONFIRM_SELECT_DISTANCE_METERS && !hasCloseRunnerUp) {
        return { kind: 'near', branch: candidate, distanceMeters: candidateDistanceMeters };
      }

      return {
        kind: candidateDistanceMeters > CONFIRM_SELECT_DISTANCE_METERS ? 'far' : 'unknown',
        branch: candidate,
        distanceMeters: candidateDistanceMeters,
      };
    } catch (error) {
      console.error('Error detectando sucursal cercana:', error);
      return { kind: 'unknown', branch: null, distanceMeters: null };
    }
  }

  function getTypesWithoutEatIn(types: TipoPedido[]): TipoPedido[] {
    return types.filter((type) => type !== 'eat_in');
  }

  function getFirstEatInBranch(): Sucursal | null {
    return sucursales.find((item) => item.tipos_entrega?.includes('eat_in')) ?? sucursales[0] ?? null;
  }

  function syncDetectedBranchIfSafe(nextBranch: Sucursal) {
    if (itemCount > 0 && cartRestaurantId !== null && cartRestaurantId !== nextBranch.id) {
      return;
    }

    seleccionar(nextBranch);
  }

  function resumeSavedTableSession(session = tableSession) {
    if (!session) {
      return false;
    }

    const savedBranch =
      session.branch ??
      sucursales.find((item) => Number(item.id) === Number(session.restauranteId)) ??
      branch ??
      null;

    if (savedBranch && Number(branch?.id) !== Number(savedBranch.id)) {
      seleccionar(savedBranch);
    }

    if (tipoPedido !== 'eat_in') {
      setTipoPedido('eat_in');
    }

    closeDeliveryFlow();
    void refreshBranchConfig(session.restauranteId, { force: true }).catch(() => undefined);
    void queryClient.invalidateQueries({ queryKey: ['menu', session.restauranteId] });
    return true;
  }

  async function openEatInScanner(branchToUse?: Sucursal | null, options?: { requestCamera?: boolean; forceScanner?: boolean }) {
    if (!options?.forceScanner && resumeSavedTableSession()) {
      return;
    }

    if (options?.requestCamera) {
      const cameraPermission = await ensureCameraPermission();
      if (!cameraPermission.granted) {
        toast.info('Necesitamos la camara para escanear el QR de tu mesa.');
        return;
      }
    }

    const branchForScanner = branchToUse ?? branch ?? getFirstEatInBranch();
    const scannerParams: { returnTo: string; mode: string; branchId?: string } = {
      returnTo: '/(tabs)',
      mode: 'eat_in',
    };

    if (branchForScanner) {
      syncDetectedBranchIfSafe(branchForScanner);
      scannerParams.branchId = String(branchForScanner.id);
    }

    setTipoPedido('eat_in');
    closeDeliveryFlow();
    router.push({
      pathname: '/table-scanner',
      params: scannerParams,
    });
  }

  // Lógica para detectar ubicación y mostrar modal inicial
  useEffect(() => {
    if (tableScanDeferred !== '1') return;

    initialFlowStartedRef.current = true;
    setShowTypeModal(false);
    setSelectingPickupBranch(false);
    setDetectedBranchMessage(null);
    setTipoPedido('eat_in');
  }, [setTipoPedido, tableScanDeferred]);

  useEffect(() => {
    if (!isPermissionsResolved || tipoPedido || sucursales.length === 0) {
      return;
    }

    if (tableSession) {
      resumeSavedTableSession(tableSession);
      return;
    }

    if (initialFlowStartedRef.current) {
      return;
    }

    initialFlowStartedRef.current = true;
    let cancelled = false;

    async function bootstrapOrderFlow() {
      const enabledTypes = getEnabledOrderTypes(sucursales);

      if (!cancelled) {
        setAvailableTypes(enabledTypes);
        setSelectingPickupBranch(false);
        setDetectedBranchMessage(null);
        setShowTypeModal(true);
      }
    }

    void bootstrapOrderFlow();

    return () => {
      cancelled = true;
    };
  }, [isPermissionsResolved, tipoPedido, sucursales, branch, seleccionar, setTipoPedido, tableSession, router]);

  async function openDeliveryFlow(prefetchLocation = true) {
    if (!isPermissionsResolved) {
      return;
    }

    const enabledTypes = getEnabledOrderTypes(sucursales);
    let nextAvailableTypes: TipoPedido[] = enabledTypes.length ? enabledTypes : ['delivery', 'pickup'];

    if (enabledTypes.includes('eat_in')) {
      const nearbyBranch = await evaluateNearbyBranch();

      if (nearbyBranch.kind === 'inside') {
        syncDetectedBranchIfSafe(nearbyBranch.branch);
        setDetectedBranchMessage(`Detectamos ${nearbyBranch.branch.nombre}. Escanea tu mesa cuando quieras pedir aquí.`);
        nextAvailableTypes = ['eat_in'];
      }

      else if (nearbyBranch.kind === 'near') {
        syncDetectedBranchIfSafe(nearbyBranch.branch);
        setDetectedBranchMessage(`Estás cerca de ${nearbyBranch.branch.nombre}. Puedes escanear tu mesa después.`);
        nextAvailableTypes = ['eat_in'];
      } else if (nearbyBranch.kind === 'far') {
        nextAvailableTypes = getTypesWithoutEatIn(enabledTypes);
        setDetectedBranchMessage('Para pedir en restaurante, acércate a una sucursal y escanea el QR de tu mesa.');
      } else {
        nextAvailableTypes = getTypesWithoutEatIn(enabledTypes);
        setDetectedBranchMessage('No pudimos confirmar que estés en restaurante. Te mostramos las opciones para pedir fuera.');
      }
    }

    setAvailableTypes(nextAvailableTypes);
    setSelectingPickupBranch(false);
    setShowTypeModal(true);

    if (prefetchLocation) {
      void getDetectedCoords({ silentOnDenied: true });
    }
  }

  function closeDeliveryFlow() {
    setShowTypeModal(false);
    setSelectingPickupBranch(false);
    setPickupListExpanded(true);
    setDetectedBranchMessage(null);
  }

  function handleScanLater() {
    let branchForMenu = menuBranch;

    if (availableTypes.includes('eat_in') || branch?.tipos_entrega?.includes('eat_in')) {
      if (!branch) {
        const fallbackBranch = menuBranch ?? getFirstEatInBranch();
        if (fallbackBranch) {
          syncDetectedBranchIfSafe(fallbackBranch);
          branchForMenu = fallbackBranch;
        }
      }
      setTipoPedido('eat_in');
      clearTableSession();

      if (branchForMenu?.id) {
        deferScan(branchForMenu);
        void refreshBranchConfig(branchForMenu.id, { force: true }).catch(() => undefined);
        void queryClient.invalidateQueries({ queryKey: ['menu', branchForMenu.id] });
      } else {
        deferScan(null);
      }
    }
    closeDeliveryFlow();
  }

  async function handleInitialTypeSelect(tipo: TipoPedido) {
    if (tipo === 'delivery') {
      try {
        setSelectingPickupBranch(false);
        setPickupListExpanded(true);
        setDetectedBranchMessage(null);
        setShowTypeModal(false);
        setShowDeliveryAddressModal(true);
      } catch (error) {
        console.error('Error preparando domicilio:', error);
        setShowDeliveryAddressModal(false);
        closeDeliveryFlow();
        Alert.alert(
          'No pudimos abrir domicilio',
          'Intenta nuevamente en un momento. Si el problema continua, puedes elegir una sucursal manualmente.'
        );
      }
      return;
    }

    if (tipo === 'pickup') {
      try {
        let permission = await Location.getForegroundPermissionsAsync();

        if (!permission.granted) {
          permission = await Location.requestForegroundPermissionsAsync();
        }

        if (permission.granted) {
          await getDetectedCoords({ silentOnDenied: true });
        } else {
          toast.info('Puedes elegir la sucursal manualmente. Activa tu ubicacion para ordenar por cercania.');
        }

        setTipoPedido('pickup');
        setSelectingPickupBranch(true);
        setPickupListExpanded(true);
      } catch (error) {
        console.error('Error preparando pickup:', error);
        Alert.alert('No pudimos preparar recoger', 'No pudimos obtener tu ubicacion, intentalo de nuevo.');
      }
      return;
    }

    if (tipo === 'eat_in') {
      try {
        const nearbyBranch = await evaluateNearbyBranch();
        if (nearbyBranch.kind !== 'inside') {
          Alert.alert(
            'Acercate a una sucursal',
            'Para escanear el QR de tu mesa necesitamos confirmar que estas dentro del restaurante.'
          );
          return;
        }

        await openEatInScanner(nearbyBranch.branch, { forceScanner: true });
      } catch (error) {
        console.error('Error preparando escaner en restaurante:', error);
        Alert.alert('No pudimos abrir el escaner', 'No pudimos validar tu ubicacion, intentalo de nuevo.');
      }
      return;
    }

    setTipoPedido(tipo);

    try {
      let targetBranch: Sucursal | undefined;
      const coords = await getDetectedCoords({ silentOnDenied: false });

      if (coords) {
        const nearest = await getNearestBranches(coords.lat, coords.lng, tipo);
        targetBranch = nearest[0];
      }

      if (!targetBranch) {
        const compatible = sucursales.filter((item) => item.tipos_entrega.includes(tipo));
        if (compatible.length === 1) {
          targetBranch = compatible[0];
        }
      }

      if (targetBranch) {
        selectBranchForType(targetBranch, tipo);
        return;
      }

      closeDeliveryFlow();
      Alert.alert(
        'Selecciona sucursal',
        'No pudimos detectar una sucursal cercana para este tipo de pedido. Elige una manualmente.',
        [
          {
            text: 'Elegir sucursal',
            onPress: () => {
              closeDeliveryFlow();
              router.push({ pathname: '/branch-selector', params: { tipoPedido: tipo } });
            },
          },
        ]
      );
    } catch (error) {
      console.error('Error seleccionando sucursal cercana:', error);
      closeDeliveryFlow();
      router.push({ pathname: '/branch-selector', params: { tipoPedido: tipo } });
    }
  }

  async function handleDeliveryAddressConfirm(address: DeliveryAddressSelection) {
    setShowDeliveryAddressModal(false);

    try {
      let targetBranch: Sucursal | undefined;
      const hasCoords = Number.isFinite(Number(address.lat)) && Number.isFinite(Number(address.lng));

      if (hasCoords) {
        const nearest = await getNearestBranches(Number(address.lat), Number(address.lng), 'delivery');
        targetBranch = nearest[0];
      }

      if (!targetBranch) {
        const compatible = sucursales.filter((item) => item.tipos_entrega.includes('delivery'));
        if (compatible.length === 1) {
          targetBranch = compatible[0];
        }
      }

      if (targetBranch) {
        selectBranchForType(targetBranch, 'delivery', address);
        return;
      }

      setDeliveryAddress(address);
      setTipoPedido('delivery');
      closeDeliveryFlow();
      Alert.alert(
        'Selecciona sucursal',
        'Guardamos tu dirección, pero no pudimos detectar una sucursal cercana. Elige una manualmente.',
        [
          {
            text: 'Elegir sucursal',
            onPress: () => {
              closeDeliveryFlow();
              router.push({ pathname: '/branch-selector', params: { tipoPedido: 'delivery' } });
            },
          },
        ]
      );
    } catch (error) {
      console.error('Error seleccionando sucursal por domicilio:', error);
      setDeliveryAddress(address);
      setTipoPedido('delivery');
      closeDeliveryFlow();
      router.push({ pathname: '/branch-selector', params: { tipoPedido: 'delivery' } });
    }
  }

  function selectBranchForType(nextBranch: Sucursal, tipo: TipoPedido, deliveryAddress?: DeliveryAddressSelection) {
    const applySelection = () => {
      if (tipo === 'delivery' && deliveryAddress) {
        setDeliveryAddress(deliveryAddress);
      }
      seleccionar(nextBranch);
      setTipoPedido(tipo);
      closeDeliveryFlow();
    };

    if (itemCount > 0 && cartRestaurantId !== null && cartRestaurantId !== nextBranch.id) {
      Alert.alert(
        'Cambiar sucursal',
        'Tu carrito tiene platillos de otra sucursal. Para cambiarla necesitamos vaciarlo.',
        [
          { text: 'Cancelar', style: 'cancel' },
          {
            text: 'Vaciar y cambiar',
            style: 'destructive',
            onPress: () => {
              clear();
              applySelection();
            },
          },
        ]
      );
      return;
    }

    applySelection();
  }

  function getEnabledOrderTypes(branches: Sucursal[]): TipoPedido[] {
    const order: TipoPedido[] = ['delivery', 'pickup', 'eat_in'];
    const found = new Set<TipoPedido>();

    branches.forEach((item) => {
      item.tipos_entrega?.forEach((type) => found.add(type));
    });

    return order.filter((type) => found.has(type));
  }

  function getOrderModeLabel() {
    if (tipoPedido === 'delivery') return 'Delivery';
    if (tipoPedido === 'pickup') return 'Pickup';
    if (tipoPedido === 'eat_in') return tableSession?.mesaLabel ? `Comer aquí · ${tableSession.mesaLabel}` : 'Comer aquí';
    return 'Elegir entrega';
  }

  function getOrderModeIcon(): keyof typeof Ionicons.glyphMap {
    if (tipoPedido === 'delivery') return 'bicycle-outline';
    if (tipoPedido === 'pickup') return 'bag-handle-outline';
    if (tipoPedido === 'eat_in') return 'restaurant-outline';
    return 'options-outline';
  }

  function getPickupBranches() {
    return sucursales
      .filter((item) => item.tipos_entrega?.includes('pickup'))
      .sort((a, b) => {
        const da = a.distancia_km ?? Number.MAX_SAFE_INTEGER;
        const db = b.distancia_km ?? Number.MAX_SAFE_INTEGER;
        return da - db;
      });
  }

  function getPickupSelectionLabel() {
    const selectedPickupBranch = getSelectedPickupBranch();

    if (!selectedPickupBranch) {
      return 'Selecciona una sucursal';
    }

    return selectedPickupBranch.nombre;
  }

  function getSelectedPickupBranch(): Sucursal | null {
    const pickupBranches = getPickupBranches();
    return pickupBranches.find((item) => item.id === branch?.id) ?? pickupBranches[0] ?? null;
  }

  function getPickupPreviewRegion() {
    const selectedPickupBranch = getSelectedPickupBranch();
    const latitude = Number(selectedPickupBranch?.lat);
    const longitude = Number(selectedPickupBranch?.lng);

    if (!selectedPickupBranch || !Number.isFinite(latitude) || !Number.isFinite(longitude)) {
      return null;
    }

    return {
      latitude,
      longitude,
      latitudeDelta: 0.01,
      longitudeDelta: 0.01,
    };
  }

  function handleSearch() {
    if (search.trim() && restauranteId) {
      router.push({ 
        pathname: '/category/[id]', 
        params: { id: 'search', q: search.trim(), restauranteId: String(restauranteId) } 
      });
    }
  }

  function handleCategory(cat: Categoria) {
    if (!restauranteId) return;
    router.push({
      pathname: '/category/[id]',
      params: { 
        id: String(cat.id), 
        restauranteId: String(restauranteId),
        nombre: cat.nombre // Enviamos el nombre para que el título no sea genérico
      },
    });
  }

  function handleDish(platillo: Platillo) {
    if (!restauranteId) return;
    const productRestaurantId = platillo.restaurante_id ?? restauranteId;
    router.push({
      pathname: '/product/[id]',
      params: { id: String(platillo.id), restauranteId: String(productRestaurantId) },
    });
  }

  const firstName = user?.nombre?.split(' ')[0] ?? '';
  const currentHour = new Date().getHours();
  const greeting = currentHour < 12 ? 'Buenos días' : currentHour < 19 ? 'Buenas tardes' : 'Buenas noches';

  const selectedPickupBranch = getSelectedPickupBranch();
  const pickupPreviewRegion = getPickupPreviewRegion();

  return (
    <SafeAreaView style={styles.safe} edges={['top']}>
      <StatusBar barStyle="dark-content" backgroundColor="#FAF8F4" />
      
      {/* HEADER ULTRA-SLIM CORREGIDO */}
      <Animated.View style={[styles.topNav, {
        opacity: homeIntro,
        transform: [{ translateY: homeIntro.interpolate({ inputRange: [0, 1], outputRange: [-14, 0] }) }],
      }]}>
        <TouchableOpacity 
          style={styles.locationSelector}
          onPress={() => {
            void openDeliveryFlow();
          }}
          activeOpacity={0.7}
        >
          <View style={styles.locationIcon}>
            <Ionicons name={getOrderModeIcon()} size={17} color="#F5EFE4" />
          </View>
          <View style={styles.locationCopy}>
            <Text style={styles.locationLabel}>{getOrderModeLabel()}</Text>
            <View style={styles.row}>
              <Text style={styles.locationName} numberOfLines={1}>
                {menuBranch?.nombre ?? 'Seleccionar sucursal'}
              </Text>
              <Ionicons name="chevron-down" size={14} color="#8A8276" />
            </View>
          </View>
        </TouchableOpacity>

        <TouchableOpacity 
          onPress={() => router.push('/(tabs)/profile')}
          style={styles.profileTrigger}
          activeOpacity={0.7}
        >
          <View style={styles.miniAvatar}>
            {user?.foto_url ? (
              <Image source={{ uri: user.foto_url }} style={styles.miniAvatarImg} />
            ) : (
              <Text style={styles.avatarText}>{user?.nombre?.[0] ?? '?'}</Text>
            )}
          </View>
        </TouchableOpacity>
      </Animated.View>

      <ScrollView 
        showsVerticalScrollIndicator={false}
        contentContainerStyle={styles.scrollContent}
        refreshControl={<RefreshControl refreshing={refreshingHome} onRefresh={() => void refreshHome()} tintColor={Colors.primary} />}
      >
        {/* SALUDO Y BUSCADOR */}
        <Animated.View style={[styles.welcomeSection, {
          opacity: homeIntro,
          transform: [{ translateY: homeIntro.interpolate({ inputRange: [0, 1], outputRange: [22, 0] }) }],
        }]}>
          <LinearGradient colors={['#202228', '#30333B']} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={styles.heroCard}>
            <Animated.View pointerEvents="none" style={[styles.heroGlow, {
              opacity: heroGlow.interpolate({ inputRange: [0, 1], outputRange: [0.12, 0.28] }),
              transform: [{ scale: heroGlow.interpolate({ inputRange: [0, 1], outputRange: [0.9, 1.12] }) }],
            }]} />
            <View style={styles.heroTopRow}>
              <View>
                <Text style={styles.heroEyebrow}>{greeting.toUpperCase()}</Text>
                <Text style={styles.greetingText}>{firstName ? `${firstName},` : 'Bienvenido,'}</Text>
              </View>
              {!(tipoPedido === 'eat_in' && tableSession) ? (
                <TouchableOpacity style={styles.modeBadge} onPress={() => void openDeliveryFlow()} activeOpacity={0.86}>
                  <Ionicons name={getOrderModeIcon()} size={14} color="#E9DDC8" />
                  <Text style={styles.modeBadgeText}>{getOrderModeLabel()}</Text>
                </TouchableOpacity>
              ) : null}
            </View>
            <Text style={styles.subtitleText}>Hoy puede empezar con algo delicioso.</Text>

            <View style={styles.searchContainer}>
              <SearchBar
                value={search}
                onChangeText={setSearch}
                onSubmit={handleSearch}
                placeholder="Buscar platillos, bebidas y más"
              />
            </View>

            <View style={styles.heroFooter}>
              <View style={styles.heroFeature}>
                <Ionicons name="sparkles-outline" size={14} color="#D7C6A8" />
                <Text style={styles.heroFeatureText}>Selección especial</Text>
              </View>
              <View style={styles.heroDivider} />
              <View style={styles.heroFeature}>
                <Ionicons name="time-outline" size={14} color="#D7C6A8" />
                <Text style={styles.heroFeatureText}>Pedido sencillo</Text>
              </View>
            </View>
          </LinearGradient>
        </Animated.View>

        {/* BANNERS */}
        <Animated.View style={[styles.bannerSection, { opacity: homeIntro }]}>
          <BannerCarousel items={HOME_BANNERS} />
        </Animated.View>

        {/* CATEGORÍAS */}
        {canShowReservations() ? (
          <View style={styles.reservationSection}>
            <LinearGradient colors={['#141414', '#25221D']} style={styles.reservationCard}>
              <View style={styles.reservationKickerRow}>
                <Ionicons name="calendar-outline" size={14} color="#D6B77A" />
                <Text style={styles.reservationKicker}>SISTEMA DE RESERVACIONES</Text>
              </View>
              <Text style={styles.reservationTitle}>Reserva tu mesa</Text>
              <Text style={styles.reservationText}>Consulta disponibilidad y separa una mesa antes de llegar.</Text>
              <TouchableOpacity style={styles.reservationButton} activeOpacity={0.86} onPress={openReservationModal}>
                <Text style={styles.reservationButtonText}>Seleccionar una mesa</Text>
                <Ionicons name="arrow-forward" size={17} color="#111111" />
              </TouchableOpacity>
            </LinearGradient>
          </View>
        ) : null}

        <View style={styles.sectionHeader}>
          <View>
            <Text style={styles.sectionKicker}>DESCUBRE</Text>
            <Text style={styles.sectionTitle}>Explorar menú</Text>
          </View>
          <View style={styles.sectionIcon}><Ionicons name="grid-outline" size={17} color={Colors.primary} /></View>
        </View>
        {loadingCats && visibleCategories.length === 0 ? (
          <View style={[styles.horizontalList, { paddingHorizontal: featuredInset }]}>
            {[1, 2, 3].map((i) => <Skeleton key={i} width={140} height={100} borderRadius={20} style={{marginRight: 12}} />)}
          </View>
        ) : (
          <FlatList
            horizontal
            data={visibleCategories}
            keyExtractor={(item) => item.id.toString()}
            showsHorizontalScrollIndicator={false}
            contentContainerStyle={[styles.horizontalList, { paddingHorizontal: featuredInset }]}
            renderItem={({ item }) => <CategoryCard categoria={item} onPress={handleCategory} />}
            removeClippedSubviews={false}
            // CategoryCard ya incluye un margen derecho interno por defecto en su componente
          />
        )}

        {/* DESTACADOS */}
        <View style={styles.sectionHeader}>
          <View>
            <Text style={styles.sectionKicker}>FAVORITOS</Text>
            <Text style={styles.sectionTitle}>Los más pedidos</Text>
          </View>
          <TouchableOpacity 
            onPress={() => {
              if (!restauranteId) return;
              router.push({
                pathname: '/category/[id]',
                params: { 
                  id: 'destacados', // O el ID específico que manejes para los destacados
                  restauranteId: String(restauranteId),
                  nombre: 'Los más pedidos'
                }
              });
            }}
          >
            <Text style={styles.seeAll}>Ver todo</Text>
          </TouchableOpacity>
        </View>
        
        {loadingMenuItems ? (
          <View style={[styles.horizontalList, { paddingHorizontal: featuredInset }]}>
            {[1, 2].map((i) => <Skeleton key={i} width={featuredCardWidth} height={260} borderRadius={25} style={{marginRight: featuredGap}} />)}
          </View>
        ) : visibleFeatured.length > 0 ? (
          <View>
            <Animated.FlatList
              horizontal
              data={visibleFeatured}
              keyExtractor={(item) => item.id.toString()}
              showsHorizontalScrollIndicator={false}
              contentContainerStyle={[styles.horizontalList, { paddingHorizontal: featuredInset }]}
              renderItem={({ item }) => <ProductCard platillo={item} onPress={handleDish} width={featuredCardWidth} />}
              ItemSeparatorComponent={() => <View style={{ width: featuredGap }} />}
              snapToInterval={featuredSnapInterval}
              snapToAlignment="start"
              decelerationRate="fast"
              bounces={false}
              removeClippedSubviews={false}
              onScroll={Animated.event(
                [{ nativeEvent: { contentOffset: { x: scrollX } } }],
                { useNativeDriver: false }
              )}
              scrollEventThrottle={16}
            />
            
            {/* 🔘 INDICADORES (DOTS) */}
            <View style={styles.pagination}>
              {visibleFeatured.map((_, i) => {
                const inputRange = [(i - 1) * featuredSnapInterval, i * featuredSnapInterval, (i + 1) * featuredSnapInterval];
                
                const dotWidth = scrollX.interpolate({
                  inputRange,
                  outputRange: [8, 20, 8],
                  extrapolate: 'clamp',
                });
                
                const opacity = scrollX.interpolate({
                  inputRange,
                  outputRange: [0.3, 1, 0.3],
                  extrapolate: 'clamp',
                });

                return <Animated.View key={i} style={[styles.dot, { width: dotWidth, opacity }]} />;
              })}
            </View>
          </View>
        ) : (
          <View style={[styles.emptyMenuBox, { marginHorizontal: featuredInset }]}>
            <Ionicons name="restaurant-outline" size={22} color="#8A7A64" />
            <Text style={styles.emptyMenuText}>No pudimos cargar platillos para esta sucursal. Desliza para actualizar.</Text>
          </View>
        )}

        {/* Espacio final interno para empujar el contenido arriba del dock */}
        <View style={styles.bottomSpacer} />
      </ScrollView>

      {/* BOTÓN FLOTANTE DE TIENDA */}
      <StoreFAB />

      {/* MODAL DE SELECCIÓN INICIAL */}
      <Modal visible={isPermissionsResolved && showTypeModal} transparent animationType="fade" onRequestClose={closeDeliveryFlow}>
        <Pressable style={styles.modalOverlay} onPress={selectingPickupBranch ? undefined : closeDeliveryFlow}>
          <Pressable style={styles.modalContent} onPress={(event) => event.stopPropagation()}>
            <Ionicons
              name={selectingPickupBranch ? 'storefront' : 'restaurant'}
              size={40}
              color={Colors.primary}
              style={{ marginBottom: 15, alignSelf: 'center' }}
            />
            <Text style={styles.modalTitle}>
              {selectingPickupBranch ? 'Elige donde recoger' : `Bienvenido a ${branch?.nombre || 'Amare'}`}
            </Text>
            <Text style={styles.modalSubtitle}>
              {selectingPickupBranch
                ? 'Selecciona una sucursal compatible con pickup.'
                : 'Como prefieres disfrutar tu comida hoy?'}
            </Text>
            {detectedBranchMessage && !selectingPickupBranch ? (
              <View style={styles.detectedBranchBox}>
                <Ionicons name="location-outline" size={16} color={Colors.primary} />
                <Text style={styles.detectedBranchText}>{detectedBranchMessage}</Text>
              </View>
            ) : null}
             
            {detectingLocation ? (
              <ActivityIndicator color={Colors.primary} style={{ marginVertical: 20 }} />
            ) : selectingPickupBranch ? (
              <View style={styles.pickupSelector}>
                <View style={styles.pickupCard}>
                  <Text style={styles.pickupCardTitle}>Pick Up</Text>
                  <Text style={styles.pickupCardSubtitle}>Elige la sucursal donde pasarás por tu pedido.</Text>

                  <View style={styles.pickupDropdownWrap}>
                    <TouchableOpacity
                      style={styles.pickupDropdownTrigger}
                      onPress={() => setPickupListExpanded((value) => !value)}
                      activeOpacity={0.85}
                    >
                      <View style={styles.pickupDropdownCopy}>
                        <Text style={styles.pickupDropdownLabel}>Sucursal</Text>
                        <Text style={styles.pickupDropdownValue} numberOfLines={1}>
                          {getPickupSelectionLabel()}
                        </Text>
                      </View>
                      <Ionicons
                        name={pickupListExpanded ? 'chevron-up' : 'chevron-down'}
                        size={18}
                        color="#6B7280"
                      />
                    </TouchableOpacity>

                    {pickupListExpanded ? (
                      <ScrollView style={styles.pickupList} contentContainerStyle={styles.pickupListContent}>
                        {getPickupBranches().map((item) => {
                          const isCurrentBranch = branch?.id === item.id;

                          return (
                            <TouchableOpacity
                              key={item.id}
                              style={[styles.pickupBranchButton, isCurrentBranch && styles.pickupBranchButtonActive]}
                              onPress={() => selectBranchForType(item, 'pickup')}
                              activeOpacity={0.85}
                            >
                              <View style={styles.pickupBranchCopy}>
                                <Text style={[styles.pickupBranchName, isCurrentBranch && styles.pickupBranchNameActive]}>
                                  {item.nombre}
                                </Text>
                                <Text style={styles.pickupBranchAddress} numberOfLines={1}>
                                  {item.direccion || item.descripcion || 'Sucursal'}
                                </Text>
                              </View>
                              {typeof item.distancia_km === 'number' && (
                                <Text style={styles.pickupBranchDistance}>{item.distancia_km.toFixed(1)} km</Text>
                              )}
                              <Ionicons
                                name={isCurrentBranch ? 'checkmark-circle' : 'chevron-forward'}
                                size={18}
                                color={isCurrentBranch ? Colors.primary : '#6B7280'}
                              />
                            </TouchableOpacity>
                          );
                        })}
                      </ScrollView>
                    ) : null}
                  </View>

                  <View style={styles.pickupMapWrap}>
                    {pickupPreviewRegion ? (
                      <View style={styles.pickupMapCard}>
                        <SafeMapView
                          style={styles.pickupMap}
                          region={pickupPreviewRegion}
                          fallbackText="Mapa no disponible por el momento."
                        />
                        <View style={styles.pickupMapPin} pointerEvents="none">
                          <Ionicons name="location" size={32} color={Colors.primary} style={{ marginTop: -32 }} />
                          <View style={styles.pickupMapPinShadow} />
                        </View>
                      </View>
                    ) : (
                      <View style={styles.pickupMapEmpty}>
                        <Ionicons name="map-outline" size={18} color="#6B7280" />
                        <Text style={styles.pickupMapEmptyText}>Mapa no disponible por el momento.</Text>
                      </View>
                    )}

                    <Text style={styles.pickupMapCaption} numberOfLines={2}>
                      {selectedPickupBranch?.direccion || selectedPickupBranch?.descripcion || 'Confirma la sucursal para recoger.'}
                    </Text>
                  </View>
                </View>

                <TouchableOpacity style={styles.modalBackButton} onPress={() => setSelectingPickupBranch(false)}>
                  <Ionicons name="arrow-back" size={16} color={Colors.primary} />
                  <Text style={styles.modalBackText}>Cambiar método</Text>
                </TouchableOpacity>
              </View>
            ) : (
              <View style={styles.selectorContainer}>
                {availableTypes.length > 0 ? (
                  <OrderTypeSelector
                    value={tipoPedido as any}
                    onChange={(tipo) => {
                      handleInitialTypeSelect(tipo);
                    }}
                    available={availableTypes}
                  />
                ) : (
                  <View style={styles.emptyTypeBox}>
                    <Ionicons name="location-outline" size={20} color="#6B7280" />
                    <Text style={styles.emptyTypeText}>
                      Acercate a una sucursal para escanear tu mesa.
                    </Text>
                  </View>
                )}
              </View>
            )}

            {!detectingLocation && !selectingPickupBranch && availableTypes.includes('eat_in') ? (
              <TouchableOpacity style={styles.scanLaterButton} activeOpacity={0.84} onPress={handleScanLater}>
                <Ionicons name="time-outline" size={17} color={Colors.primary} />
                <Text style={styles.scanLaterButtonText}>Escanear más tarde</Text>
              </TouchableOpacity>
            ) : null}

            {false && availableTypes.length === 2 && !detectingLocation && !selectingPickupBranch && (
              <View style={styles.infoBox}>
                <Ionicons name="information-circle-outline" size={16} color="#6B7280" />
                <Text style={styles.infoText}>
                  La opción "En mesa" solo está disponible si te encuentras en el restaurante.
                </Text>
              </View>
            )}
          </Pressable>
        </Pressable>
      </Modal>
      <DeliveryAddressModal
        visible={showDeliveryAddressModal}
        onDismiss={() => setShowDeliveryAddressModal(false)}
        onConfirm={handleDeliveryAddressConfirm}
      />

      <Modal visible={reservationVisible} transparent animationType="slide" onRequestClose={() => setReservationVisible(false)}>
        <View style={styles.reservationModalOverlay}>
          <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : 'height'} style={styles.reservationKeyboard}>
          <View style={styles.reservationModal}>
            <View style={styles.reservationModalHeader}>
              <View>
                <Text style={styles.reservationModalKicker}>SISTEMA DE RESERVACIONES</Text>
                <Text style={styles.reservationModalTitle}>Reserva tu mesa</Text>
              </View>
              <TouchableOpacity style={styles.reservationCloseButton} onPress={() => setReservationVisible(false)}>
                <Ionicons name="close" size={20} color="#F7F1E7" />
              </TouchableOpacity>
            </View>

            <ScrollView
              showsVerticalScrollIndicator={false}
              contentContainerStyle={styles.reservationForm}
              keyboardShouldPersistTaps="handled"
              keyboardDismissMode="interactive"
            >
              <TextInput
                style={styles.reservationInput}
                value={reservationName}
                onChangeText={setReservationName}
                placeholder="Tu nombre completo"
                placeholderTextColor="#8A8276"
              />
              <View style={styles.reservationFormRow}>
                <TextInput
                  style={[styles.reservationInput, styles.reservationHalfInput]}
                  value={reservationPhone}
                  onChangeText={setReservationPhone}
                  placeholder="Telefono"
                  keyboardType="phone-pad"
                  placeholderTextColor="#8A8276"
                />
                <TextInput
                  style={[styles.reservationInput, styles.reservationHalfInput]}
                  value={reservationEmail}
                  onChangeText={setReservationEmail}
                  placeholder="Correo"
                  keyboardType="email-address"
                  autoCapitalize="none"
                  placeholderTextColor="#8A8276"
                />
              </View>
              <View style={styles.reservationFormRow}>
                <TouchableOpacity
                  style={styles.reservationPickerButton}
                  onPress={() => openReservationPicker('date')}
                  activeOpacity={0.82}
                  accessibilityRole="button"
                  accessibilityLabel="Seleccionar fecha de reservación"
                >
                  <Ionicons name="calendar-outline" size={19} color="#D6B77A" />
                  <View style={styles.reservationPickerCopy}>
                    <Text style={styles.reservationPickerLabel}>Fecha</Text>
                    <Text style={styles.reservationPickerValue} numberOfLines={1} adjustsFontSizeToFit>
                      {formatReservationDateLabel(reservationDate)}
                    </Text>
                  </View>
                  <Ionicons name="chevron-down" size={16} color="#8A8276" />
                </TouchableOpacity>
                <TouchableOpacity
                  style={styles.reservationPickerButton}
                  onPress={() => openReservationPicker('time')}
                  activeOpacity={0.82}
                  accessibilityRole="button"
                  accessibilityLabel="Seleccionar hora de reservación"
                >
                  <Ionicons name="time-outline" size={19} color="#D6B77A" />
                  <View style={styles.reservationPickerCopy}>
                    <Text style={styles.reservationPickerLabel}>Hora</Text>
                    <Text style={styles.reservationPickerValue} numberOfLines={1} adjustsFontSizeToFit>
                      {formatReservationTimeLabel(reservationDate, reservationTime)}
                    </Text>
                  </View>
                  <Ionicons name="chevron-down" size={16} color="#8A8276" />
                </TouchableOpacity>
              </View>

              <View style={styles.peopleSelector}>
                <Text style={styles.reservationLabel}>Numero de personas</Text>
                <View style={styles.peopleStepper}>
                  <TouchableOpacity style={styles.peopleStepButton} onPress={() => changeReservationPeople(-1)}>
                    <Ionicons name="remove" size={16} color="#E9DDC8" />
                  </TouchableOpacity>
                  <Text style={styles.peopleValue}>{reservationPeople} personas</Text>
                  <TouchableOpacity style={styles.peopleStepButton} onPress={() => changeReservationPeople(1)}>
                    <Ionicons name="add" size={16} color="#E9DDC8" />
                  </TouchableOpacity>
                </View>
              </View>

              <TouchableOpacity style={styles.checkTablesButton} onPress={() => void refreshReservationTables()} disabled={reservationLoading}>
                {reservationLoading ? (
                  <ActivityIndicator color="#111111" />
                ) : (
                  <Text style={styles.checkTablesButtonText}>Consultar mesas disponibles</Text>
                )}
              </TouchableOpacity>

              <View style={styles.availableTablesBox}>
                <Text style={styles.reservationLabel}>Mesa disponible</Text>
                {reservationTables.length > 0 ? (
                  <View style={styles.availableTablesList}>
                    {reservationTables.map((item) => {
                      const selected = selectedReservationTableId === item.id;
                      return (
                        <TouchableOpacity
                          key={item.id}
                          style={[styles.availableTableChip, selected && styles.availableTableChipActive]}
                          onPress={() => setSelectedReservationTableId(item.id)}
                        >
                          <Text style={[styles.availableTableText, selected && styles.availableTableTextActive]}>
                            {item.label}{item.capacity ? ` · ${item.capacity}p` : ''}
                          </Text>
                        </TouchableOpacity>
                      );
                    })}
                  </View>
                ) : (
                  <Text style={styles.availableTablesEmpty}>Elige fecha, hora y personas para consultar mesas.</Text>
                )}
              </View>

              <TextInput
                style={[styles.reservationInput, styles.reservationNotesInput]}
                value={reservationNotes}
                onChangeText={setReservationNotes}
                placeholder="Alergias, ocasion especial o preferencias"
                multiline
                placeholderTextColor="#8A8276"
              />

              <TouchableOpacity
                style={[styles.submitReservationButton, (!selectedReservationTableId || reservationSubmitting) && styles.submitReservationButtonDisabled]}
                onPress={() => void submitReservation()}
                disabled={!selectedReservationTableId || reservationSubmitting}
              >
                {reservationSubmitting ? (
                  <ActivityIndicator color="#111111" />
                ) : (
                  <Text style={styles.submitReservationButtonText}>Reservar mesa</Text>
                )}
              </TouchableOpacity>
            </ScrollView>
          </View>
          </KeyboardAvoidingView>
        </View>
      </Modal>

      {Platform.OS === 'ios' ? (
        <Modal
          visible={reservationPickerMode !== null}
          transparent
          animationType="fade"
          onRequestClose={() => setReservationPickerMode(null)}
        >
          <View style={styles.reservationPickerOverlay}>
            <View style={styles.reservationPickerSheet}>
              <View style={styles.reservationPickerHeader}>
                <TouchableOpacity onPress={() => setReservationPickerMode(null)} activeOpacity={0.8}>
                  <Text style={styles.reservationPickerCancel}>Cancelar</Text>
                </TouchableOpacity>
                <Text style={styles.reservationPickerTitle}>
                  {reservationPickerMode === 'date' ? 'Selecciona la fecha' : 'Selecciona la hora'}
                </Text>
                <TouchableOpacity onPress={confirmReservationPicker} activeOpacity={0.8}>
                  <Text style={styles.reservationPickerDone}>Listo</Text>
                </TouchableOpacity>
              </View>
              {reservationPickerMode ? (
                <DateTimePicker
                  value={reservationPickerDraft}
                  mode={reservationPickerMode}
                  display={reservationPickerMode === 'date' ? 'inline' : 'spinner'}
                  minimumDate={reservationPickerMode === 'date' ? new Date() : undefined}
                  maximumDate={reservationPickerMode === 'date' ? new Date(new Date().setFullYear(new Date().getFullYear() + 1)) : undefined}
                  minuteInterval={RESERVATION_MINUTE_STEP}
                  locale="es-MX"
                  themeVariant="dark"
                  accentColor="#D6B77A"
                  onChange={(_event, selected) => {
                    if (selected) setReservationPickerDraft(selected);
                  }}
                  style={styles.reservationNativePicker}
                />
              ) : null}
            </View>
          </View>
        </Modal>
      ) : null}
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: '#FAF8F4' },
  topNav: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: 20,
    paddingTop: 10,
    paddingBottom: 13,
    backgroundColor: '#FAF8F4',
    zIndex: 50,
  },
  locationSelector: { flexDirection: 'row', alignItems: 'center', flex: 1, minWidth: 0, paddingVertical: 4 },
  locationIcon: { width: 38, height: 38, borderRadius: 14, alignItems: 'center', justifyContent: 'center', backgroundColor: '#25282E' },
  locationCopy: { flex: 1, minWidth: 0, marginLeft: 10 },
  locationLabel: { fontSize: 9, color: '#978F83', fontWeight: '800', textTransform: 'uppercase', letterSpacing: 1.1 },
  locationName: { fontSize: 15, fontWeight: '900', color: '#24262B', marginRight: 4, flexShrink: 1 },
  row: { flexDirection: 'row', alignItems: 'center' },
  profileTrigger: { marginLeft: 15, padding: 2 },
  miniAvatar: { width: 40, height: 40, borderRadius: 15, backgroundColor: '#EEE9E0', alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: '#DED7CC', overflow: 'hidden' },
  miniAvatarImg: { width: '100%', height: '100%' },
  avatarText: { fontWeight: '900', color: Colors.primary },

  scrollContent: { paddingTop: 4, paddingBottom: 176 },
  welcomeSection: { paddingHorizontal: 16, marginTop: 6, marginBottom: 8 },
  heroCard: {
    borderRadius: 30,
    paddingHorizontal: 20,
    paddingTop: 22,
    paddingBottom: 16,
    overflow: 'hidden',
    shadowColor: '#121317',
    shadowOffset: { width: 0, height: 12 },
    shadowOpacity: 0.2,
    shadowRadius: 22,
    elevation: 8,
  },
  heroGlow: { position: 'absolute', width: 210, height: 210, borderRadius: 105, backgroundColor: '#D0B17D', top: -130, right: -70 },
  heroTopRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start', gap: 12 },
  heroEyebrow: { color: '#BBAE98', fontSize: 9, fontWeight: '900', letterSpacing: 1.8, marginBottom: 3 },
  greetingText: { fontFamily: 'PlayfairDisplay_700Bold', fontSize: 31, color: '#F7F1E7', letterSpacing: -0.6 },
  subtitleText: { fontSize: 13, color: '#BEB6A9', marginTop: 4 },
  modeBadge: { maxWidth: 142, minHeight: 34, borderRadius: 17, paddingHorizontal: 10, flexDirection: 'row', alignItems: 'center', gap: 5, backgroundColor: 'rgba(233,221,200,0.1)', borderWidth: 1, borderColor: 'rgba(233,221,200,0.13)' },
  modeBadgeText: { flexShrink: 1, color: '#E9DDC8', fontSize: 10, fontWeight: '800' },
  searchContainer: { marginTop: 18 },
  heroFooter: { flexDirection: 'row', alignItems: 'center', marginTop: 14, paddingHorizontal: 3 },
  heroFeature: { flex: 1, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 6 },
  heroFeatureText: { color: '#AAA294', fontSize: 10, fontWeight: '700' },
  heroDivider: { width: 1, height: 15, backgroundColor: 'rgba(233,221,200,0.14)' },

  orderTypeWrapper: {
    marginVertical: 10,
  },

  bannerSection: { marginTop: 20 },

  sectionHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: 20,
    marginTop: 30,
    marginBottom: 12,
  },
  sectionKicker: { color: '#A18D6E', fontSize: 9, fontWeight: '900', letterSpacing: 1.8, marginBottom: 3 },
  sectionTitle: {
    fontFamily: 'PlayfairDisplay_700Bold',
    fontSize: 22,
    color: '#25272C',
    letterSpacing: -0.4,
  },
  sectionIcon: { width: 38, height: 38, borderRadius: 14, backgroundColor: '#EEE8DE', alignItems: 'center', justifyContent: 'center' },
  seeAll: {
    color: '#7C6748',
    fontWeight: '900',
    fontSize: 12,
    paddingVertical: 8,
  },
  horizontalList: {
    paddingVertical: 10, // Importante: Da aire para que las sombras no se corten
  },
  reservationSection: {
    paddingHorizontal: 20,
    marginTop: 18,
  },
  reservationCard: {
    borderRadius: 24,
    padding: 18,
    overflow: 'hidden',
  },
  reservationKickerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    marginBottom: 8,
  },
  reservationKicker: {
    color: '#D6B77A',
    fontSize: 9,
    fontWeight: '900',
    letterSpacing: 1.8,
  },
  reservationTitle: {
    fontFamily: 'PlayfairDisplay_700Bold',
    fontSize: 27,
    color: '#F7F1E7',
  },
  reservationText: {
    color: '#C7BFB1',
    fontSize: 13,
    lineHeight: 18,
    marginTop: 4,
  },
  reservationButton: {
    minHeight: 46,
    borderRadius: 16,
    backgroundColor: '#D6B77A',
    marginTop: 16,
    paddingHorizontal: 14,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
  },
  reservationButtonText: {
    color: '#111111',
    fontSize: 12,
    fontWeight: '900',
    letterSpacing: 1.8,
    textTransform: 'uppercase',
  },
  pagination: {
    flexDirection: 'row',
    justifyContent: 'center',
    alignItems: 'center',
    marginTop: 15,
    gap: 6,
  },
  dot: {
    height: 8,
    borderRadius: 4,
    backgroundColor: Colors.primary,
  },
  emptyMenuBox: {
    minHeight: 118,
    borderRadius: 18,
    backgroundColor: '#F1ECE4',
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 20,
    gap: 8,
  },
  emptyMenuText: {
    color: '#7C6748',
    fontSize: 13,
    fontWeight: '700',
    textAlign: 'center',
  },
  bottomSpacer: {
    height: 24,
  },
  reservationModalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.62)',
    justifyContent: 'flex-end',
  },
  reservationKeyboard: {
    flex: 1,
    justifyContent: 'flex-end',
  },
  reservationModal: {
    maxHeight: '88%',
    backgroundColor: '#111111',
    borderTopLeftRadius: 28,
    borderTopRightRadius: 28,
    paddingHorizontal: 18,
    paddingTop: 18,
    paddingBottom: 18,
  },
  reservationModalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    gap: 12,
    marginBottom: 10,
  },
  reservationModalKicker: {
    color: '#D6B77A',
    fontSize: 9,
    fontWeight: '900',
    letterSpacing: 1.8,
  },
  reservationModalTitle: {
    fontFamily: 'PlayfairDisplay_700Bold',
    fontSize: 30,
    color: '#F7F1E7',
    marginTop: 4,
  },
  reservationCloseButton: {
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: '#242424',
    alignItems: 'center',
    justifyContent: 'center',
  },
  reservationForm: {
    gap: 12,
    paddingBottom: 20,
  },
  reservationInput: {
    minHeight: 48,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: '#2C2C2C',
    backgroundColor: '#171717',
    color: '#F7F1E7',
    paddingHorizontal: 14,
    fontSize: 14,
    fontWeight: '700',
  },
  reservationFormRow: {
    flexDirection: 'row',
    gap: 10,
  },
  reservationHalfInput: {
    flex: 1,
    minWidth: 0,
  },
  reservationPickerButton: {
    flex: 1,
    minWidth: 0,
    minHeight: 58,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: '#2C2C2C',
    backgroundColor: '#171717',
    paddingHorizontal: 12,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 9,
  },
  reservationPickerCopy: {
    flex: 1,
    minWidth: 0,
  },
  reservationPickerLabel: {
    color: '#8A8276',
    fontSize: 10,
    fontWeight: '800',
    textTransform: 'uppercase',
  },
  reservationPickerValue: {
    color: '#F7F1E7',
    fontSize: 14,
    fontWeight: '800',
    marginTop: 2,
  },
  reservationPickerOverlay: {
    flex: 1,
    justifyContent: 'flex-end',
    backgroundColor: 'rgba(0,0,0,0.62)',
  },
  reservationPickerSheet: {
    backgroundColor: '#171717',
    borderTopLeftRadius: 24,
    borderTopRightRadius: 24,
    borderWidth: 1,
    borderColor: '#2C2C2C',
    paddingHorizontal: 16,
    paddingTop: 14,
    paddingBottom: 24,
  },
  reservationPickerHeader: {
    minHeight: 42,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 12,
  },
  reservationPickerTitle: {
    flex: 1,
    color: '#F7F1E7',
    fontSize: 15,
    fontWeight: '900',
    textAlign: 'center',
  },
  reservationPickerCancel: {
    color: '#B9B0A3',
    fontSize: 14,
    fontWeight: '700',
  },
  reservationPickerDone: {
    color: '#D6B77A',
    fontSize: 14,
    fontWeight: '900',
  },
  reservationNativePicker: {
    width: '100%',
  },
  reservationLabel: {
    color: '#D6B77A',
    fontSize: 10,
    fontWeight: '900',
    letterSpacing: 1.5,
    textTransform: 'uppercase',
  },
  peopleSelector: {
    gap: 8,
  },
  peopleStepper: {
    minHeight: 48,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: '#2C2C2C',
    backgroundColor: '#171717',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 10,
  },
  peopleStepButton: {
    width: 34,
    height: 34,
    borderRadius: 17,
    backgroundColor: '#252525',
    alignItems: 'center',
    justifyContent: 'center',
  },
  peopleValue: {
    color: '#F7F1E7',
    fontSize: 14,
    fontWeight: '800',
  },
  checkTablesButton: {
    minHeight: 48,
    borderRadius: 16,
    backgroundColor: '#D6B77A',
    alignItems: 'center',
    justifyContent: 'center',
  },
  checkTablesButtonText: {
    color: '#111111',
    fontSize: 12,
    fontWeight: '900',
    letterSpacing: 1.4,
    textTransform: 'uppercase',
  },
  availableTablesBox: {
    borderRadius: 16,
    borderWidth: 1,
    borderColor: '#2C2C2C',
    padding: 12,
    gap: 10,
  },
  availableTablesList: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  availableTableChip: {
    borderRadius: 999,
    borderWidth: 1,
    borderColor: '#343434',
    backgroundColor: '#181818',
    paddingHorizontal: 12,
    paddingVertical: 9,
  },
  availableTableChipActive: {
    backgroundColor: '#D6B77A',
    borderColor: '#D6B77A',
  },
  availableTableText: {
    color: '#D8D1C7',
    fontSize: 12,
    fontWeight: '800',
  },
  availableTableTextActive: {
    color: '#111111',
  },
  availableTablesEmpty: {
    color: '#8A8276',
    fontSize: 13,
    lineHeight: 18,
  },
  reservationNotesInput: {
    minHeight: 88,
    paddingTop: 12,
    textAlignVertical: 'top',
  },
  submitReservationButton: {
    minHeight: 52,
    borderRadius: 18,
    backgroundColor: '#D6B77A',
    alignItems: 'center',
    justifyContent: 'center',
  },
  submitReservationButtonDisabled: {
    opacity: 0.5,
  },
  submitReservationButtonText: {
    color: '#111111',
    fontSize: 12,
    fontWeight: '900',
    letterSpacing: 2,
    textTransform: 'uppercase',
  },
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.6)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: 20,
  },
  modalContent: {
    backgroundColor: '#FFF',
    borderRadius: 30,
    paddingHorizontal: 22,
    paddingTop: 26,
    paddingBottom: 18,
    width: '100%',
    maxWidth: 420,
    alignItems: 'stretch',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 10 },
    shadowOpacity: 0.25,
    shadowRadius: 15,
    elevation: 10,
  },
  modalTitle: {
    fontSize: 22,
    fontWeight: '800',
    color: '#111827',
    textAlign: 'center',
    alignSelf: 'center',
  },
  modalSubtitle: {
    fontSize: 15,
    color: '#6B7280',
    textAlign: 'center',
    marginTop: 8,
    marginBottom: 16,
    alignSelf: 'center',
  },
  selectorContainer: {
    width: '100%',
    marginTop: 8,
    marginBottom: 4,
  },
  detectedBranchBox: {
    width: '100%',
    borderRadius: 16,
    borderWidth: 1,
    borderColor: '#E7EAF0',
    backgroundColor: '#F9FAFC',
    paddingHorizontal: 12,
    paddingVertical: 10,
    marginBottom: 10,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  detectedBranchText: {
    flex: 1,
    minWidth: 0,
    fontSize: 12,
    lineHeight: 17,
    fontWeight: '700',
    color: '#4B5563',
  },
  scanLaterButton: {
    width: '100%',
    minHeight: 44,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: '#D8DDE8',
    backgroundColor: '#FFFFFF',
    alignItems: 'center',
    justifyContent: 'center',
    flexDirection: 'row',
    gap: 8,
    marginTop: 10,
  },
  scanLaterButtonText: {
    fontSize: 14,
    fontWeight: '800',
    color: Colors.primary,
  },
  emptyTypeBox: {
    width: '100%',
    minHeight: 68,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    backgroundColor: '#F9FAFB',
    alignItems: 'center',
    justifyContent: 'center',
    flexDirection: 'row',
    gap: 8,
    paddingHorizontal: 14,
  },
  emptyTypeText: {
    flex: 1,
    minWidth: 0,
    color: '#4B5563',
    fontSize: 13,
    fontWeight: '700',
    lineHeight: 18,
  },
  pickupSelector: {
    width: '100%',
    gap: 12,
  },
  pickupCard: {
    width: '100%',
    borderRadius: 24,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    backgroundColor: '#FCFCFD',
    padding: 14,
    gap: 12,
  },
  pickupCardTitle: {
    fontSize: 18,
    fontWeight: '800',
    color: '#111827',
  },
  pickupCardSubtitle: {
    display: 'none',
  },
  pickupDropdownWrap: {
    width: '100%',
    borderRadius: 18,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    backgroundColor: '#FFFFFF',
    overflow: 'hidden',
  },
  pickupDropdownTrigger: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 14,
    paddingVertical: 12,
  },
  pickupDropdownCopy: {
    flex: 1,
    minWidth: 0,
    marginRight: 12,
  },
  pickupDropdownLabel: {
    fontSize: 11,
    fontWeight: '700',
    color: '#9CA3AF',
    textTransform: 'uppercase',
    marginBottom: 4,
  },
  pickupDropdownValue: {
    fontSize: 15,
    fontWeight: '700',
    color: '#111827',
  },
  pickupList: {
    width: '100%',
    maxHeight: 210,
    borderTopWidth: 1,
    borderTopColor: '#F3F4F6',
  },
  pickupListContent: {
    gap: 8,
    padding: 10,
  },
  pickupBranchButton: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 12,
    borderRadius: 12,
    backgroundColor: '#F9FAFB',
    borderWidth: 1,
    borderColor: '#E5E7EB',
    gap: 10,
  },
  pickupBranchButtonActive: {
    borderColor: '#D7B98F',
    backgroundColor: '#FFF7ED',
  },
  pickupBranchCopy: {
    flex: 1,
    minWidth: 0,
  },
  pickupBranchName: {
    fontSize: 14,
    fontWeight: '700',
    color: '#111827',
  },
  pickupBranchNameActive: {
    color: Colors.primary,
  },
  pickupBranchAddress: {
    fontSize: 12,
    color: '#6B7280',
    marginTop: 2,
  },
  pickupBranchDistance: {
    fontSize: 12,
    fontWeight: '700',
    color: Colors.primary,
  },
  pickupMapWrap: {
    gap: 10,
  },
  pickupMapCard: {
    height: 156,
    borderRadius: 18,
    overflow: 'hidden',
    backgroundColor: '#F3F4F6',
    borderWidth: 1,
    borderColor: '#E5E7EB',
    justifyContent: 'center',
  },
  pickupMap: {
    flex: 1,
  },
  pickupMapPin: {
    position: 'absolute',
    top: '50%',
    left: '50%',
    marginLeft: -16,
    marginTop: -6,
    alignItems: 'center',
  },
  pickupMapPinShadow: {
    width: 12,
    height: 12,
    borderRadius: 6,
    backgroundColor: 'rgba(17,24,39,0.18)',
    marginTop: -4,
  },
  pickupMapEmpty: {
    minHeight: 84,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    borderStyle: 'dashed',
    backgroundColor: '#F9FAFB',
    alignItems: 'center',
    justifyContent: 'center',
    flexDirection: 'row',
    gap: 8,
    paddingHorizontal: 14,
  },
  pickupMapEmptyText: {
    color: '#6B7280',
    fontSize: 13,
    fontWeight: '700',
  },
  pickupMapCaption: {
    fontSize: 12,
    lineHeight: 18,
    color: '#4B5563',
  },
  modalBackButton: {
    flexDirection: 'row',
    alignItems: 'center',
    alignSelf: 'center',
    gap: 6,
    paddingVertical: 8,
  },
  modalBackText: {
    fontSize: 13,
    fontWeight: '700',
    color: Colors.primary,
  },
  detectedBranchBanner: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    backgroundColor: '#F9FAFB',
    borderWidth: 1,
    borderColor: '#E5E7EB',
    borderRadius: 12,
    paddingHorizontal: 12,
    paddingVertical: 10,
    marginTop: 14,
    marginBottom: 6,
  },
  infoBox: {
    flexDirection: 'row',
    backgroundColor: '#F3F4F6',
    padding: 12,
    borderRadius: 12,
    marginTop: 20,
    alignItems: 'center',
    gap: 8,
  },
  infoText: {
    fontSize: 12,
    color: '#6B7280',
    flex: 1,
  },
});
