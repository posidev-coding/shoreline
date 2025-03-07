import { pgTable, text, serial, timestamp } from "drizzle-orm/pg-core";
import { createInsertSchema } from "drizzle-zod";
import { z } from "zod";

export const contactSubmissions = pgTable("contact_submissions", {
  id: serial("id").primaryKey(),
  name: text("name").notNull(),
  email: text("email").notNull(),
  phone: text("phone").notNull(),
  message: text("message").notNull(),
  service: text("service").notNull(),
  submittedAt: timestamp("submitted_at").defaultNow(),
});

export const insertContactSchema = createInsertSchema(contactSubmissions)
  .pick({
    name: true,
    email: true,
    phone: true,
    message: true,
    service: true,
  })
  .extend({
    email: z.string().email("Please enter a valid email address"),
    phone: z.string().min(10, "Please enter a valid phone number"),
    message: z.string().min(10, "Message must be at least 10 characters"),
  });

export type InsertContact = z.infer<typeof insertContactSchema>;
export type Contact = typeof contactSubmissions.$inferSelect;
