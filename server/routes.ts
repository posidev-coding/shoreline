import type { Express } from "express";
import { createServer } from "http";
import { insertContactSchema } from "@shared/schema";
import { storage } from "./storage";

import { sendMail } from "./mailService";

export async function registerRoutes(app: Express) {
  app.post("/api/contact", async (req, res) => {

    console.log('Attempting to send an email');
    console.log(req.body);

    try {
      const from: string = 'coxta45@gmail.com';
      const to: string = req.body.email;
      const subject: string = 'New Contact Request';
      let mailTemplate: string = 'Someone just submitted a request to get in touch...<br><br>';

      mailTemplate += "<div>";

      mailTemplate += `<h4>Name</h4><p>${req.body.name}</p><br>`;
      mailTemplate += `<h4>Email</h4><p>${req.body.email}</p><br>`;
      mailTemplate += `<h4>Phone</h4><p>${req.body.phone}</p><br>`;
      mailTemplate += `<h4>Service</h4><p>${req.body.service}</p><br>`;
      mailTemplate += `<h4>Message</h4><p>${req.body.message}</p>`;

      mailTemplate += "</div>";

      sendMail( from, to, subject, mailTemplate);

      res.json({ success: true, data: req.body });
    } catch (error) {
      res.status(400).json({ success: false, error: String(error) });
    }

    // try {
    //   const contact = insertContactSchema.parse(req.body);
    //   const submission = await storage.createContact(contact);
    //   res.json({ success: true, data: submission });
    // } catch (error) {
    //   res.status(400).json({ success: false, error: String(error) });
    // }
  });

  return createServer(app);
}
