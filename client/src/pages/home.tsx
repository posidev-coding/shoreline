import Navbar from "@/components/layout/navbar";
import Hero from "@/components/sections/hero";
import Services from "@/components/sections/services";
import Gallery from "@/components/sections/gallery";
import Testimonials from "@/components/sections/testimonials";
import Contact from "@/components/sections/contact";

export default function Home() {
  return (
    <div className="min-h-screen bg-background">
      <Navbar />
      <main>
        <Hero />
        <Services />
        <Gallery />
        <Testimonials />
        <Contact />
      </main>
      <footer className="bg-primary text-primary-foreground py-4 text-center">
        <p>© {new Date().getFullYear()} Elite Garage Doors. All rights reserved.</p>
      </footer>
    </div>
  );
}
