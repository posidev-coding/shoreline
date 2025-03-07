import { motion } from "framer-motion";
import { AspectRatio } from "@/components/ui/aspect-ratio";

const images = [
  {
    src: "https://images.unsplash.com/photo-1512917774080-9991f1c4c750",
    alt: "Modern craftsman beach house with custom garage door",
  },
  {
    src: "https://images.unsplash.com/photo-1568605114967-8130f3a36994",
    alt: "Contemporary beach home with designer garage door",
  },
  {
    src: "https://images.unsplash.com/photo-1564013799919-ab600027ffc6",
    alt: "Luxury beachfront property with premium garage door",
  },
  {
    src: "https://images.unsplash.com/photo-1570129477492-45c003edd2be",
    alt: "Coastal residence with high-end garage installation",
  },
];

export default function Gallery() {
  return (
    <section id="gallery" className="py-20">
      <div className="container mx-auto px-4">
        <div className="text-center mb-12">
          <h2 className="text-3xl font-bold mb-4">Our Work</h2>
          <p className="text-muted-foreground max-w-2xl mx-auto">
            Browse through our portfolio of completed installations
          </p>
        </div>

        <div className="grid md:grid-cols-2 gap-6">
          {images.map((image, index) => (
            <motion.div
              key={image.src}
              initial={{ opacity: 0, scale: 0.95 }}
              whileInView={{ opacity: 1, scale: 1 }}
              transition={{ delay: index * 0.1 }}
              viewport={{ once: true }}
              className="overflow-hidden rounded-lg"
            >
              <AspectRatio ratio={16 / 9}>
                <img
                  src={image.src}
                  alt={image.alt}
                  className="object-cover w-full h-full hover:scale-105 transition-transform duration-300"
                />
              </AspectRatio>
            </motion.div>
          ))}
        </div>
      </div>
    </section>
  );
}