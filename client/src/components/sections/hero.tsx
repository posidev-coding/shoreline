import { Button } from "@/components/ui/button";
import { motion } from "framer-motion";

export default function Hero() {
  return (
    <section className="relative min-h-[calc(100vh-4rem)] flex items-center">
      <div
        className="absolute inset-0 z-0"
        style={{
          backgroundImage: `linear-gradient(to right, rgba(0,0,0,0.8), rgba(0,0,0,0.4)), url(https://images.unsplash.com/photo-1739700712159-550519327ca7)`,
          backgroundSize: "cover",
          backgroundPosition: "center",
        }}
      />
      <div className="container mx-auto px-4 relative z-10">
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.8 }}
          className="max-w-2xl text-white"
        >
          <h1 className="text-4xl md:text-6xl font-bold mb-6">
            Professional Garage Door Solutions
          </h1>
          <p className="text-xl mb-8 text-gray-200">
            Expert installation, repair, and maintenance services for your home or business.
            Quality workmanship guaranteed.
          </p>
          <div className="flex gap-4">
            <Button size="lg" asChild>
              <a href="#contact">Get Free Quote</a>
            </Button>
            <Button size="lg" variant="outline" asChild>
              <a href="#services">Our Services</a>
            </Button>
          </div>
        </motion.div>
      </div>
    </section>
  );
}
