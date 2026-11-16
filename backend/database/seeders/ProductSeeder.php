<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = [
            ['sku' => 'ELEC-0001', 'name' => 'Laptop Ultradelgada 14" 16GB/512GB', 'category' => 'Laptops', 'price' => 799.99, 'stock' => 25, 'description' => 'Laptop ligera con procesador de última generación, 16GB RAM y SSD de 512GB.'],
            ['sku' => 'ELEC-0002', 'name' => 'Laptop Gamer 15.6" RTX 16GB/1TB', 'category' => 'Laptops', 'price' => 1349.00, 'stock' => 12, 'description' => 'Laptop para gaming con tarjeta gráfica dedicada, pantalla 144Hz y SSD de 1TB.'],
            ['sku' => 'ELEC-0003', 'name' => 'Smartphone 6.5" 128GB', 'category' => 'Celulares', 'price' => 349.99, 'stock' => 60, 'description' => 'Smartphone con cámara triple, pantalla AMOLED y batería de larga duración.'],
            ['sku' => 'ELEC-0004', 'name' => 'Smartphone Pro Max 256GB', 'category' => 'Celulares', 'price' => 999.00, 'stock' => 30, 'description' => 'Modelo tope de gama con cámara de 108MP y carga rápida de 65W.'],
            ['sku' => 'ELEC-0005', 'name' => 'Tablet 10.1" 64GB WiFi', 'category' => 'Tablets', 'price' => 229.99, 'stock' => 40, 'description' => 'Tablet ideal para lectura, entretenimiento y productividad ligera.'],
            ['sku' => 'ELEC-0006', 'name' => 'Smartwatch Deportivo GPS', 'category' => 'Wearables', 'price' => 129.99, 'stock' => 50, 'description' => 'Reloj inteligente con monitor de ritmo cardíaco, GPS y resistencia al agua.'],
            ['sku' => 'ELEC-0007', 'name' => 'Audífonos Inalámbricos con Cancelación de Ruido', 'category' => 'Audio', 'price' => 149.99, 'stock' => 80, 'description' => 'Audífonos over-ear con cancelación activa de ruido y 30h de batería.'],
            ['sku' => 'ELEC-0008', 'name' => 'Audífonos In-Ear Bluetooth', 'category' => 'Audio', 'price' => 39.99, 'stock' => 120, 'description' => 'Earbuds inalámbricos con estuche de carga y resistencia al sudor.'],
            ['sku' => 'ELEC-0009', 'name' => 'Bocina Portátil Bluetooth Resistente al Agua', 'category' => 'Audio', 'price' => 59.99, 'stock' => 70, 'description' => 'Bocina portátil con sonido 360°, certificación IPX7 y 12h de autonomía.'],
            ['sku' => 'ELEC-0010', 'name' => 'Mouse Inalámbrico Ergonómico', 'category' => 'Accesorios', 'price' => 19.99, 'stock' => 150, 'description' => 'Mouse inalámbrico con sensor óptico de alta precisión.'],
            ['sku' => 'ELEC-0011', 'name' => 'Teclado Mecánico RGB', 'category' => 'Accesorios', 'price' => 69.99, 'stock' => 55, 'description' => 'Teclado mecánico con switches táctiles e iluminación RGB personalizable.'],
            ['sku' => 'ELEC-0012', 'name' => 'Monitor LED 27" Full HD 75Hz', 'category' => 'Monitores', 'price' => 179.99, 'stock' => 35, 'description' => 'Monitor de 27 pulgadas con panel IPS y tasa de refresco de 75Hz.'],
            ['sku' => 'ELEC-0013', 'name' => 'Monitor Curvo 32" QHD', 'category' => 'Monitores', 'price' => 349.00, 'stock' => 18, 'description' => 'Monitor curvo de alta resolución ideal para productividad y gaming.'],
            ['sku' => 'ELEC-0014', 'name' => 'Disco SSD Externo 1TB USB-C', 'category' => 'Almacenamiento', 'price' => 89.99, 'stock' => 65, 'description' => 'Almacenamiento externo de alta velocidad con conexión USB-C.'],
            ['sku' => 'ELEC-0015', 'name' => 'Memoria USB 128GB 3.1', 'category' => 'Almacenamiento', 'price' => 14.99, 'stock' => 200, 'description' => 'Memoria flash de alta velocidad para transferencia de archivos.'],
            ['sku' => 'ELEC-0016', 'name' => 'Power Bank 20000mAh Carga Rápida', 'category' => 'Accesorios', 'price' => 34.99, 'stock' => 90, 'description' => 'Batería portátil de alta capacidad con carga rápida bidireccional.'],
            ['sku' => 'ELEC-0017', 'name' => 'Cámara Web Full HD 1080p', 'category' => 'Accesorios', 'price' => 49.99, 'stock' => 45, 'description' => 'Webcam con micrófono integrado y corrección automática de luz.'],
            ['sku' => 'ELEC-0018', 'name' => 'Router WiFi 6 Doble Banda', 'category' => 'Redes', 'price' => 99.99, 'stock' => 28, 'description' => 'Router de alto rendimiento con soporte WiFi 6 y cobertura ampliada.'],
            ['sku' => 'ELEC-0019', 'name' => 'Impresora Multifuncional WiFi', 'category' => 'Oficina', 'price' => 129.99, 'stock' => 20, 'description' => 'Impresora, escáner y copiadora con conectividad WiFi.'],
            ['sku' => 'ELEC-0020', 'name' => 'Consola de Videojuegos Nueva Generación', 'category' => 'Gaming', 'price' => 499.00, 'stock' => 10, 'description' => 'Consola de última generación con soporte 4K y almacenamiento SSD.'],
        ];

        foreach ($products as $index => $product) {
            Product::query()->updateOrCreate(
                ['sku' => $product['sku']],
                [
                    'name' => $product['name'],
                    'description' => $product['description'],
                    'category' => $product['category'],
                    'price' => $product['price'],
                    'stock' => $product['stock'],
                    'image_url' => 'https://picsum.photos/seed/product'.($index + 1).'/600/400',
                    'is_active' => true,
                ]
            );
        }
    }
}
