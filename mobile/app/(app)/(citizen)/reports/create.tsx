import React, { useState, useEffect } from 'react';
import { View, Text, TextInput, Button, StyleSheet, ActivityIndicator, ScrollView, Platform, Image, TouchableOpacity } from 'react-native';
import { useRouter } from 'expo-router';
import * as ImagePicker from 'expo-image-picker';
import MapWidget, { MarkerPoint } from '../../../../src/components/MapWidget';
import { api } from '../../../../src/services/api';

interface Category {
  id: number;
  name: string;
}

const SimplePicker = ({ selectedValue, onValueChange, items, enabled = true }: any) => {
  return (
    <View style={styles.simplePicker}>
      {items.map((item: any) => (
        <TouchableOpacity
          key={item.value ?? 'null-val'}
          disabled={!enabled}
          style={[styles.pickerItem, selectedValue === item.value && styles.pickerItemSelected]}
          onPress={() => onValueChange(item.value)}
        >
          <Text style={selectedValue === item.value ? styles.pickerItemTextSelected : styles.pickerItemText}>
            {item.label}
          </Text>
        </TouchableOpacity>
      ))}
    </View>
  );
};



export default function CreateReportScreen() {
  const router = useRouter();

  const [categories, setCategories] = useState<Category[]>([]);
  const [loadingCategories, setLoadingCategories] = useState(true);
  
  const [categoryId, setCategoryId] = useState<number | null>(null);
  const [description, setDescription] = useState('');
  const [visibility, setVisibility] = useState<'public' | 'private' | 'anonymous'>('public');
  const [location, setLocation] = useState<MarkerPoint | null>(null);
  
  const [photoUri, setPhotoUri] = useState<string | null>(null);
  const [photoBlob, setPhotoBlob] = useState<Blob | null>(null);
  const [photoExt, setPhotoExt] = useState<string | null>(null);
  
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [errors, setErrors] = useState<{ [key: string]: string }>({});
  const [success, setSuccess] = useState(false);

  useEffect(() => {
    async function fetchCategories() {
      try {
        const response = await api.get('/categories');
        setCategories(response.data);
      } catch (err) {
        console.error('Failed to fetch categories:', err);
      } finally {
        setLoadingCategories(false);
      }
    }
    fetchCategories();

    // Try to get geolocation on web
    if (Platform.OS === 'web' && navigator.geolocation) {
      navigator.geolocation.getCurrentPosition(
        (position) => {
          if (!location) {
            setLocation({
              latitude: position.coords.latitude,
              longitude: position.coords.longitude,
            });
          }
        },
        () => {
          // silent failure, fall back to default
        }
      );
    }
  }, []);

  const pickImage = async () => {
    try {
      let result = await ImagePicker.launchImageLibraryAsync({
        mediaTypes: ['images'],
        quality: 1,
        allowsEditing: false, // simpler for web compatibility
      });

      if (!result.canceled && result.assets && result.assets.length > 0) {
        const asset = result.assets[0];
        setPhotoUri(asset.uri);
        
        // On web, we need to convert the data URL or blob URL to a real Blob for upload
        if (Platform.OS === 'web') {
          try {
            const response = await fetch(asset.uri);
            const blob = await response.blob();
            setPhotoBlob(blob);
            
            // Extract extension from mime type
            const mimeType = asset.mimeType || blob.type;
            let ext = 'jpg';
            if (mimeType === 'image/png') ext = 'png';
            if (mimeType === 'image/webp') ext = 'webp';
            if (mimeType === 'image/jpeg') ext = 'jpg';
            setPhotoExt(ext);
          } catch (e) {
            console.error('Error fetching blob from uri:', e);
          }
        }
      }
    } catch (err) {
      console.error('Error picking image:', err);
    }
  };

  const validate = () => {
    const newErrors: { [key: string]: string } = {};
    if (!categoryId) newErrors.categoryId = 'Kategori wajib dipilih';
    if (!description || description.trim().length < 10) newErrors.description = 'Deskripsi minimal 10 karakter';
    if (!location) newErrors.location = 'Lokasi wajib dipilih';
    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async () => {
    if (!validate()) return;

    setIsSubmitting(true);
    setErrors({});

    try {
      // 1. Create Report
      const reportRes = await api.post('/reports', {
        category_id: categoryId,
        description,
        latitude: location!.latitude,
        longitude: location!.longitude,
        visibility,
      });

      const reportId = reportRes.data.id;

      // 2. Upload photo if selected
      if (photoUri) {
        const formData = new FormData();
        formData.append('report_id', String(reportId));
        formData.append('type', 'submission');

        if (Platform.OS === 'web' && photoBlob) {
          formData.append('file', photoBlob, `upload.${photoExt || 'jpg'}`);
        } else {
          // Native FormData append
          const filename = photoUri.split('/').pop() || 'upload.jpg';
          const match = /\.(\w+)$/.exec(filename);
          const type = match ? `image/${match[1]}` : 'image/jpeg';
          
          formData.append('file', {
            uri: photoUri,
            name: filename,
            type,
          } as any);
        }

        // Need to override headers for FormData
        await api.post('/attachments', formData, {
          headers: {
            'Content-Type': 'multipart/form-data',
          },
        });
      }

      setSuccess(true);
      setTimeout(() => {
        router.back();
      }, 1500);

    } catch (err: any) {
      console.error('Submit error:', err);
      setErrors({ submit: err.error?.message || 'Terjadi kesalahan saat mengirim laporan' });
    } finally {
      setIsSubmitting(false);
    }
  };

  if (success) {
    return (
      <View style={styles.centered}>
        <Text style={styles.successText}>Laporan Terkirim</Text>
      </View>
    );
  }

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      <Text style={styles.title}>Buat Laporan</Text>

      {/* Category */}
      <Text style={styles.label}>Kategori</Text>
      <View style={styles.pickerContainer}>
        <SimplePicker
          selectedValue={categoryId}
          onValueChange={(val: any) => setCategoryId(val)}
          enabled={!loadingCategories}
          items={[
            { label: 'Pilih Kategori', value: null },
            ...categories.map(c => ({ label: c.name, value: c.id }))
          ]}
        />
      </View>
      {errors.categoryId && <Text style={styles.errorText}>{errors.categoryId}</Text>}

      {/* Description */}
      <Text style={styles.label}>Deskripsi</Text>
      <TextInput
        style={styles.textArea}
        multiline
        numberOfLines={4}
        value={description}
        onChangeText={setDescription}
        placeholder="Tuliskan detail kejadian (min. 10 karakter)"
      />
      {errors.description && <Text style={styles.errorText}>{errors.description}</Text>}

      {/* Visibility */}
      <Text style={styles.label}>Visibilitas</Text>
      <View style={styles.pickerContainer}>
        <SimplePicker
          selectedValue={visibility}
          onValueChange={(val: any) => setVisibility(val)}
          items={[
            { label: 'Publik', value: 'public' },
            { label: 'Privat', value: 'private' },
            { label: 'Anonim', value: 'anonymous' }
          ]}
        />
      </View>

      {/* Map / Location */}
      <Text style={styles.label}>Lokasi</Text>
      <Text style={styles.helpText}>Ketuk pada peta untuk memilih lokasi</Text>
      <View style={styles.mapContainer}>
        <MapWidget
          style={styles.map}
          initialCenter={location || undefined}
          markers={location ? [location] : []}
          onPointSelect={(point) => setLocation(point)}
        />
      </View>
      {errors.location && <Text style={styles.errorText}>{errors.location}</Text>}

      {/* Photo Upload */}
      <Text style={styles.label}>Foto</Text>
      <Button title="Pilih Foto" onPress={pickImage} />
      {photoUri && (
        <Image source={{ uri: photoUri }} style={styles.previewImage} />
      )}

      {/* Submit Error */}
      {errors.submit && <Text style={styles.errorText}>{errors.submit}</Text>}

      {/* Submit Button */}
      <View style={styles.submitContainer}>
        {isSubmitting ? (
          <ActivityIndicator size="large" color="#0000ff" />
        ) : (
          <Button title="Kirim Laporan" onPress={handleSubmit} />
        )}
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#fff',
  },
  content: {
    padding: 20,
  },
  centered: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  title: {
    fontSize: 24,
    fontWeight: 'bold',
    marginBottom: 20,
  },
  label: {
    fontSize: 16,
    fontWeight: '600',
    marginTop: 15,
    marginBottom: 5,
  },
  helpText: {
    fontSize: 12,
    color: '#666',
    marginBottom: 10,
  },
  pickerContainer: {
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: 8,
    overflow: 'hidden',
    backgroundColor: '#f9f9f9',
  },
  textArea: {
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: 8,
    padding: 10,
    minHeight: 100,
    textAlignVertical: 'top',
    backgroundColor: '#f9f9f9',
  },
  mapContainer: {
    height: 250,
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: 8,
    overflow: 'hidden',
  },
  map: {
    flex: 1,
    width: '100%',
    height: '100%',
  },
  errorText: {
    color: 'red',
    fontSize: 12,
    marginTop: 5,
  },
  previewImage: {
    width: '100%',
    height: 200,
    marginTop: 10,
    borderRadius: 8,
    resizeMode: 'cover',
  },
  submitContainer: {
    marginTop: 30,
    marginBottom: 50,
  },
  successText: {
    fontSize: 20,
    fontWeight: 'bold',
    color: 'green',
  },
  simplePicker: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    padding: 5,
  },
  pickerItem: {
    paddingHorizontal: 12,
    paddingVertical: 8,
    margin: 4,
    borderRadius: 20,
    backgroundColor: '#eee',
  },
  pickerItemSelected: {
    backgroundColor: '#007AFF',
  },
  pickerItemText: {
    color: '#333',
  },
  pickerItemTextSelected: {
    color: '#fff',
    fontWeight: 'bold',
  }
});
