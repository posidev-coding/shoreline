import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { motion } from "framer-motion";
import { Wrench, Settings, Shield, Clock } from "lucide-react";

const services = [
  {
    title: "Installation",
    description: "Professional installation of new garage doors with expert precision",
    icon: Wrench,
  },
  {
    title: "Repair",
    description: "Quick and reliable repair services for all garage door types",
    icon: Settings,
  },
  {
    title: "Maintenance",
    description: "Regular maintenance to keep your garage door running smoothly",
    icon: Shield,
  },
  {
    title: "24/7 Service",
    description: "Emergency repair services available around the clock",
    icon: Clock,
  },
];

export default function Services() {
  return (
    <section id="services" className="py-20 bg-muted">
      <div className="container mx-auto px-4">
        <div className="text-center mb-12">
          <h2 className="text-3xl font-bold mb-4">Our Services</h2>
          <p className="text-muted-foreground max-w-2xl mx-auto">
            We provide comprehensive garage door services to meet all your needs
          </p>
        </div>

        <div className="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
          {services.map((service, index) => (
            <motion.div
              key={service.title}
              initial={{ opacity: 0, y: 20 }}
              whileInView={{ opacity: 1, y: 0 }}
              transition={{ delay: index * 0.1 }}
              viewport={{ once: true }}
            >
              <Card>
                <CardHeader>
                  <service.icon className="h-10 w-10 text-primary mb-4" />
                  <CardTitle>{service.title}</CardTitle>
                </CardHeader>
                <CardContent>
                  <p className="text-muted-foreground">{service.description}</p>
                </CardContent>
              </Card>
            </motion.div>
          ))}
        </div>
      </div>
    </section>
  );
}