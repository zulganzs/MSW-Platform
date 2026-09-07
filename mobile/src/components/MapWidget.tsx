import React from 'react';
import { Platform, View, Text, StyleSheet } from 'react-native';

export interface MarkerPoint {
  id?: string | number;
  latitude: number;
  longitude: number;
  title?: string;
  description?: string;
}

export interface MapWidgetProps {
  markers?: MarkerPoint[];
  initialCenter?: MarkerPoint;
  onPointSelect?: (point: MarkerPoint) => void;
  onMarkerPress?: (marker: MarkerPoint) => void;
  style?: any;
}

export default function MapWidget({ markers = [], initialCenter, onPointSelect, onMarkerPress, style }: MapWidgetProps) {
  if (Platform.OS === 'web') {
    return <WebMap markers={markers} initialCenter={initialCenter} onPointSelect={onPointSelect} onMarkerPress={onMarkerPress} style={style} />;
  } else {
    return <NativeMap markers={markers} initialCenter={initialCenter} onPointSelect={onPointSelect} onMarkerPress={onMarkerPress} style={style} />;
  }
}

function WebMap({ markers, initialCenter, onPointSelect, onMarkerPress, style }: MapWidgetProps) {
  // Use a lazy require hidden in the web branch so Metro doesn't statically resolve react-leaflet for native
  let MapContainer: any, TileLayer: any, Marker: any, Popup: any, useMapEvents: any;
  try {
    // Lazy-required inside web branch so Metro doesn't statically resolve it for native builds.
    require('leaflet/dist/leaflet.css');
    const rl = require('react-leaflet');
    MapContainer = rl.MapContainer;
    TileLayer = rl.TileLayer;
    Marker = rl.Marker;
    Popup = rl.Popup;
    useMapEvents = rl.useMapEvents;
  } catch (e) {
    return (
      <View style={[styles.fallback, style]}>
        <Text>Map requires react-leaflet to be installed</Text>
      </View>
    );
  }

  // Sensible default (Jakarta)
  const defaultCenter = { latitude: -6.2, longitude: 106.8 };
  const center = initialCenter || defaultCenter;

  const MapEvents = () => {
    useMapEvents({
      click(e: any) {
        if (onPointSelect) {
          onPointSelect({ latitude: e.latlng.lat, longitude: e.latlng.lng });
        }
      },
    });
    return null;
  };

  return (
    <View style={[styles.webContainer, style]}>
      {/* 
        Leaflet map needs a defined height on its container. 
        react-leaflet creates a div, we need to pass styles to it using style prop.
      */}
      <MapContainer 
        center={[center.latitude, center.longitude]} 
        zoom={13} 
        style={{ height: '100%', width: '100%' }}
      >
        <TileLayer
          attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
          url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
        />
        <MapEvents />
        {markers?.map((marker, index) => (
          <Marker 
            key={marker.id || index} 
            position={[marker.latitude, marker.longitude]}
            eventHandlers={{
              click: () => onMarkerPress && onMarkerPress(marker)
            }}
          >
            {(marker.title || marker.description) && (
              <Popup>
                <View>
                  {marker.title && <Text style={{fontWeight: 'bold', marginBottom: 4}}>{marker.title}</Text>}
                  {marker.description && <Text>{marker.description}</Text>}
                </View>
              </Popup>
            )}
          </Marker>
        ))}
      </MapContainer>
    </View>
  );
}

function NativeMap({ markers, initialCenter, onPointSelect, onMarkerPress, style }: MapWidgetProps) {
  let MapView: any, Marker: any;
  try {
    const rnm = require('react-native-maps');
    MapView = rnm.default || rnm;
    Marker = rnm.Marker;
  } catch (e) {
    return (
      <View style={[styles.fallback, style]}>
        <Text>Map requires react-native-maps to be installed</Text>
      </View>
    );
  }

  const defaultCenter = { latitude: -6.2, longitude: 106.8 };
  const center = initialCenter || defaultCenter;

  return (
    <MapView
      style={[styles.nativeContainer, style]}
      initialRegion={{
        latitude: center.latitude,
        longitude: center.longitude,
        latitudeDelta: 0.0922,
        longitudeDelta: 0.0421,
      }}
      onPress={(e: any) => {
        // e.nativeEvent.action check avoids triggering map click when clicking a marker
        if (onPointSelect && e.nativeEvent.coordinate && e.nativeEvent.action !== 'marker-press') {
          onPointSelect({
            latitude: e.nativeEvent.coordinate.latitude,
            longitude: e.nativeEvent.coordinate.longitude,
          });
        }
      }}
    >
      {markers?.map((marker, index) => (
        <Marker
          key={marker.id || index}
          coordinate={{ latitude: marker.latitude, longitude: marker.longitude }}
          title={marker.title}
          description={marker.description}
          onPress={() => onMarkerPress && onMarkerPress(marker)}
        />
      ))}
    </MapView>
  );
}

const styles = StyleSheet.create({
  fallback: {
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#eee',
    padding: 20,
  },
  webContainer: {
    overflow: 'hidden', // to contain the leaflet map
  },
  nativeContainer: {
    // handled by style prop passed from parent
  }
});
